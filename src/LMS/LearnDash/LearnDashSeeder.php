<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Seeder for the LearnDash LMS plugin.
 *
 * Overrides course/quiz seeding to maintain ld_course_steps and quiz_pro_id meta.
 */
class LearnDashSeeder extends AbstractSeeder
{
    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'sfwd-courses',
            'lessons'      => 'sfwd-lessons',
            'quizzes'      => 'sfwd-quiz',
            'certificates' => 'sfwd-certificates',
            default        => 'post',
        };
    }

    protected function getTitlePrefix(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'LearnDash Course',
            'lessons'      => 'LearnDash Lesson',
            'quizzes'      => 'LearnDash Quiz',
            'certificates' => 'LearnDash Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function afterCourseCreated(int $postId, int $index, array $options = []): void
    {
        $this->initializeCourseSteps($postId);
    }

    protected function afterLessonCreated(int $postId, int $courseId, int $index, array $options = []): void
    {
        if ($courseId > 0) {
            $this->addLessonToCourseSteps($courseId, $postId);
        }
    }

    protected function afterQuizCreated(int $postId, int $lessonId, int $index, array $options = []): void
    {
        $this->assignQuizProId($postId);

        $courseId = (int) ($options['course_id'] ?? 0);

        if ($courseId <= 0 && $lessonId > 0) {
            $courseId = (int) get_post_meta($lessonId, 'course_id', true);
        }

        if ($courseId > 0 && $lessonId > 0) {
            $this->addQuizToCourseSteps($courseId, $lessonId, $postId);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        if (!isset($options['course_id']) && $lessonId > 0) {
            $options['course_id'] = (int) get_post_meta($lessonId, 'course_id', true);
        }

        return parent::seedQuizzes($count, $lessonId, $options);
    }

    private function initializeCourseSteps(int $courseId): void
    {
        $steps = [
            'steps' => [
                'h' => [
                    'sfwd-lessons' => [],
                ],
            ],
            'versions' => [],
            'empty'  => [],
        ];

        update_post_meta($courseId, 'ld_course_steps', $steps);
    }

    private function addLessonToCourseSteps(int $courseId, int $lessonId): void
    {
        $steps = get_post_meta($courseId, 'ld_course_steps', true);

        if (!is_array($steps)) {
            $this->initializeCourseSteps($courseId);
            $steps = get_post_meta($courseId, 'ld_course_steps', true);
        }

        if (!is_array($steps)) {
            return;
        }

        if (!isset($steps['steps']['h']['sfwd-lessons']) || !is_array($steps['steps']['h']['sfwd-lessons'])) {
            $steps['steps']['h']['sfwd-lessons'] = [];
        }

        $steps['steps']['h']['sfwd-lessons'][$lessonId] = [
            'sfwd-topic' => [],
            'sfwd-quiz'  => [],
        ];

        update_post_meta($courseId, 'ld_course_steps', $steps);
    }

    private function addQuizToCourseSteps(int $courseId, int $lessonId, int $quizId): void
    {
        $steps = get_post_meta($courseId, 'ld_course_steps', true);

        if (!is_array($steps)) {
            return;
        }

        if (!isset($steps['steps']['h']['sfwd-lessons'][$lessonId])) {
            $this->addLessonToCourseSteps($courseId, $lessonId);
            $steps = get_post_meta($courseId, 'ld_course_steps', true);
        }

        if (!is_array($steps)) {
            return;
        }

        if (!isset($steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-quiz']) || !is_array($steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-quiz'])) {
            $steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-quiz'] = [];
        }

        $steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-quiz'][$quizId] = [];

        update_post_meta($courseId, 'ld_course_steps', $steps);
    }

    private function assignQuizProId(int $quizPostId): void
    {
        // LearnDash links the quiz post to Pro Quiz storage via quiz_pro_id.
        // When Pro Quiz APIs are unavailable, a stable placeholder keeps LD admin coherent.
        $proId = (int) get_post_meta($quizPostId, 'quiz_pro_id', true);

        if ($proId <= 0) {
            update_post_meta($quizPostId, 'quiz_pro_id', $quizPostId);
        }
    }
}
