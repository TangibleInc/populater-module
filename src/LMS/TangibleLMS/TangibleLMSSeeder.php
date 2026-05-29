<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the Tangible LMS plugin.
 *
 * Creates tgl_module posts per course so lessons attach to valid course structure.
 */
class TangibleLMSSeeder extends AbstractSeeder
{
    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $schema           = $this->plugin->getEntitySchema();
        $lessonIndex      = (int) ($options['index'] ?? 1);
        $lessonsPerCourse = max(1, (int) ($options['lessons_per_course'] ?? 1));
        $modulesPerCourse = max(1, (int) ($options['modules_per_course'] ?? 1));
        $moduleIndex      = $this->resolveContainerIndex($lessonIndex, $lessonsPerCourse, $modulesPerCourse);
        $moduleId         = $this->ensureModuleForCourse($courseId, $moduleIndex, $options);

        $index = $lessonIndex;
        $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('lessons'), $index);
        $postId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $this->getPostType('lessons'),
            'post_status'  => 'publish',
            'post_content' => DummyContent::lesson($title, $index),
            'post_excerpt' => DummyContent::excerpt('lesson', $title, $index),
            'post_parent'  => $moduleId > 0 ? $moduleId : $courseId,
        ]);

        if ($postId <= 0) {
            return [];
        }

        $this->applyMeta($postId, $this->getMetaFor('lessons', [
            'courseId' => $courseId,
            'moduleId' => $moduleId,
        ]));

        return [$postId];
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuizzes(int $count, int $parentId, array $options = []): array
    {
        $moduleId = (int) ($options['quiz_parent_id'] ?? $parentId);
        $courseId = (int) ($options['course_id'] ?? 0);

        if ($courseId <= 0 && $moduleId > 0) {
            $courseId = (int) get_post_meta($moduleId, '_tgl_course_id', true);
        }

        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $index = (int) ($options['index'] ?? $i);
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('quizzes'), $index);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('quizzes'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::quiz($title, $index),
                'post_parent'  => $moduleId > 0 ? $moduleId : 0,
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('quizzes', [
                    'moduleId' => $moduleId,
                    'courseId' => $courseId,
                ]));
                $this->afterQuizCreated($postId, $moduleId, $index, $options);
                $this->seedQuestionsForQuiz($postId, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function ensureModuleForCourse(int $courseId, int $moduleIndex, array $options): int
    {
        $schema    = $this->plugin->getEntitySchema();
        $container = $schema->container;

        if ($container === null) {
            return 0;
        }

        $existing = (int) ($options['module_id'] ?? 0);

        if ($existing > 0) {
            return $existing;
        }

        $cached = get_post_meta($courseId, $container->cacheMetaKey, true);

        if (is_array($cached) && isset($cached[$moduleIndex])) {
            return (int) $cached[$moduleIndex];
        }

        $title    = $this->defaultTitle($this->getTitlePrefix($container->entity), $moduleIndex);
        $moduleId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $schema->getPostType($container->entity),
            'post_status'  => 'publish',
            'post_content' => DummyContent::module($title, $moduleIndex),
            'post_excerpt' => DummyContent::excerpt('module', $title, $moduleIndex),
            'post_parent'  => $courseId,
        ]);

        if ($moduleId > 0) {
            update_post_meta($moduleId, $container->parentMetaKey, $courseId);

            if (!is_array($cached)) {
                $cached = [];
            }

            $cached[$moduleIndex] = $moduleId;
            update_post_meta($courseId, $container->cacheMetaKey, $cached);

            $this->recordModuleInIdMap($moduleId, $moduleIndex, $options);
        }

        return $moduleId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function recordModuleInIdMap(int $moduleId, int $moduleIndex, array $options): void
    {
        $processId = (string) ($options['process_id'] ?? '');

        if ($processId === '') {
            return;
        }

        SeedingIdMap::recordQuizParent(
            $processId,
            'modules',
            (int) ($options['course_index'] ?? 0),
            $moduleIndex,
            $moduleId,
            new ProcessRepository(),
        );
    }

    private function resolveContainerIndex(int $itemIndex, int $itemsPerContainer, int $containers): int
    {
        $containers = max(1, min($containers, $itemsPerContainer));

        return (int) min($containers, max(1, (int) ceil($itemIndex * $containers / $itemsPerContainer)));
    }
}
