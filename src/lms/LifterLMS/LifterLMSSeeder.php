<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Seeder for the LifterLMS plugin.
 *
 * Creates an llms_section per course so lessons attach to valid course structure.
 */
class LifterLMSSeeder extends AbstractSeeder
{
    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'course',
            'lessons'      => 'lesson',
            'quizzes'      => 'llms_quiz',
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
            'certificates' => 'LifterLMS Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'lessons' => ['_llms_parent_course' => (int) ($context['courseId'] ?? 0)],
            'quizzes' => ['_llms_lesson_id' => (int) ($context['lessonId'] ?? 0)],
            default   => parent::getMetaFor($entity, $context),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $sectionId = $this->ensureSectionForCourse($courseId, $options);
        $options['section_id'] = $sectionId;

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $postId = $this->insertPost([
                'post_title'   => $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('lessons'), $i),
                'post_type'    => $this->getPostType('lessons'),
                'post_status'  => 'publish',
                'post_content' => sprintf('Sample LifterLMS lesson %d content.', $i),
                'post_parent'  => $sectionId > 0 ? $sectionId : $courseId,
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('lessons', ['courseId' => $courseId]));
                if ($sectionId > 0) {
                    update_post_meta($postId, '_llms_parent_section', $sectionId);
                }
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function ensureSectionForCourse(int $courseId, array $options): int
    {
        $existing = (int) ($options['section_id'] ?? 0);

        if ($existing > 0) {
            return $existing;
        }

        $cached = (int) get_post_meta($courseId, '_populater_llms_section_id', true);

        if ($cached > 0) {
            return $cached;
        }

        $sectionId = $this->insertPost([
            'post_title'  => 'Section 1',
            'post_type'   => 'llms_section',
            'post_status' => 'publish',
            'post_parent' => $courseId,
        ]);

        if ($sectionId > 0) {
            update_post_meta($sectionId, '_llms_parent_course', $courseId);
            update_post_meta($courseId, '_populater_llms_section_id', $sectionId);
        }

        return $sectionId;
    }
}
