<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LifterLMS plugin.
 *
 * Creates llms_section posts per course so lessons attach to valid course structure.
 */
class LifterLMSSeeder extends AbstractSeeder
{
    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'course',
            'lessons'      => 'lesson',
            'quizzes'      => 'llms_quiz',
            'questions'    => 'llms_question',
            'certificates' => 'llms_certificate',
            default        => 'post',
        };
    }

    protected function getTitlePrefix(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'LifterLMS Course',
            'lessons'      => 'LifterLMS Lesson',
            'quizzes'      => 'LifterLMS Quiz',
            'questions'    => 'LifterLMS Question',
            'certificates' => 'LifterLMS Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'lessons'   => ['_llms_parent_course' => (int) ($context['courseId'] ?? 0)],
            'quizzes'   => ['_llms_lesson_id' => (int) ($context['lessonId'] ?? 0)],
            'questions' => ['_llms_parent_id' => (int) ($context['quizId'] ?? 0)],
            default     => parent::getMetaFor($entity, $context),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $lessonIndex      = (int) ($options['index'] ?? 1);
        $lessonsPerCourse = max(1, (int) ($options['lessons_per_course'] ?? 1));
        $sectionsPerCourse = max(1, (int) ($options['sections_per_course'] ?? 1));
        $sectionIndex     = $this->resolveContainerIndex($lessonIndex, $lessonsPerCourse, $sectionsPerCourse);
        $sectionId        = $this->ensureSectionForCourse($courseId, $sectionIndex, $options);

        $index = $lessonIndex;
        $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('lessons'), $index);
        $postId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $this->getPostType('lessons'),
            'post_status'  => 'publish',
            'post_content' => DummyContent::lesson($title, $index),
            'post_excerpt' => DummyContent::excerpt('lesson', $title, $index),
            'post_parent'  => $sectionId > 0 ? $sectionId : $courseId,
        ]);

        if ($postId <= 0) {
            return [];
        }

        $this->applyMeta($postId, $this->getMetaFor('lessons', ['courseId' => $courseId]));

        if ($sectionId > 0) {
            update_post_meta($postId, '_llms_parent_section', $sectionId);
        }

        return [$postId];
    }

    protected function afterQuestionCreated(int $postId, int $quizId, int $index, array $options = []): void
    {
        update_post_meta($postId, '_llms_question_type', 'true_false');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function ensureSectionForCourse(int $courseId, int $sectionIndex, array $options): int
    {
        $existing = (int) ($options['section_id'] ?? 0);

        if ($existing > 0) {
            return $existing;
        }

        $cached = get_post_meta($courseId, '_populater_llms_section_ids', true);

        if (is_array($cached) && isset($cached[$sectionIndex])) {
            return (int) $cached[$sectionIndex];
        }

        $title   = $this->defaultTitle('LifterLMS Section', $sectionIndex);
        $sectionId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => 'llms_section',
            'post_status'  => 'publish',
            'post_content' => DummyContent::section($title, $sectionIndex),
            'post_excerpt' => DummyContent::excerpt('section', $title, $sectionIndex),
            'post_parent'  => $courseId,
        ]);

        if ($sectionId > 0) {
            update_post_meta($sectionId, '_llms_parent_course', $courseId);

            if (!is_array($cached)) {
                $cached = [];
            }

            $cached[$sectionIndex] = $sectionId;
            update_post_meta($courseId, '_populater_llms_section_ids', $cached);
        }

        return $sectionId;
    }

    private function resolveContainerIndex(int $itemIndex, int $itemsPerContainer, int $containers): int
    {
        $containers = max(1, min($containers, $itemsPerContainer));

        return (int) min($containers, max(1, (int) ceil($itemIndex * $containers / $itemsPerContainer)));
    }
}
