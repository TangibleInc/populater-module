<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the Tangible LMS plugin.
 *
 * Creates tgl_module posts per course so lessons attach to valid course structure.
 */
class TangibleLMSSeeder extends AbstractSeeder
{
    private const PT_COURSE      = 'tgl_course';
    private const PT_MODULE      = 'tgl_module';
    private const PT_LESSON      = 'tgl_lesson';
    private const PT_QUIZ        = 'tgl_quiz';
    private const PT_QUESTION    = 'tgl_question';
    private const PT_CERTIFICATE = 'tgl_certificate';

    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => self::PT_COURSE,
            'modules'      => self::PT_MODULE,
            'lessons'      => self::PT_LESSON,
            'quizzes'      => self::PT_QUIZ,
            'questions'    => self::PT_QUESTION,
            'certificates' => self::PT_CERTIFICATE,
            default        => 'post',
        };
    }

    protected function getTitlePrefix(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'Tangible Course',
            'modules'      => 'Tangible Module',
            'lessons'      => 'Tangible Lesson',
            'quizzes'      => 'Tangible Quiz',
            'questions'    => 'Tangible Question',
            'certificates' => 'Tangible Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'lessons'   => [
                '_tgl_course_id' => (int) ($context['courseId'] ?? 0),
                '_tgl_module_id' => (int) ($context['moduleId'] ?? 0),
            ],
            'quizzes'   => ['_tgl_lesson_id' => (int) ($context['lessonId'] ?? 0)],
            'questions' => ['_tgl_quiz_id' => (int) ($context['quizId'] ?? 0)],
            default     => parent::getMetaFor($entity, $context),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $lessonIndex       = (int) ($options['index'] ?? 1);
        $lessonsPerCourse  = max(1, (int) ($options['lessons_per_course'] ?? 1));
        $modulesPerCourse  = max(1, (int) ($options['modules_per_course'] ?? 1));
        $moduleIndex       = $this->resolveContainerIndex($lessonIndex, $lessonsPerCourse, $modulesPerCourse);
        $moduleId          = $this->ensureModuleForCourse($courseId, $moduleIndex, $options);

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
     */
    private function ensureModuleForCourse(int $courseId, int $moduleIndex, array $options): int
    {
        $existing = (int) ($options['module_id'] ?? 0);

        if ($existing > 0) {
            return $existing;
        }

        $cached = get_post_meta($courseId, '_populater_tgl_module_ids', true);

        if (is_array($cached) && isset($cached[$moduleIndex])) {
            return (int) $cached[$moduleIndex];
        }

        $title    = $this->defaultTitle($this->getTitlePrefix('modules'), $moduleIndex);
        $moduleId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => self::PT_MODULE,
            'post_status'  => 'publish',
            'post_content' => DummyContent::module($title, $moduleIndex),
            'post_excerpt' => DummyContent::excerpt('module', $title, $moduleIndex),
            'post_parent'  => $courseId,
        ]);

        if ($moduleId > 0) {
            update_post_meta($moduleId, '_tgl_course_id', $courseId);

            if (!is_array($cached)) {
                $cached = [];
            }

            $cached[$moduleIndex] = $moduleId;
            update_post_meta($courseId, '_populater_tgl_module_ids', $cached);
        }

        return $moduleId;
    }

    private function resolveContainerIndex(int $itemIndex, int $itemsPerContainer, int $containers): int
    {
        $containers = max(1, min($containers, $itemsPerContainer));

        return (int) min($containers, max(1, (int) ceil($itemIndex * $containers / $itemsPerContainer)));
    }
}
