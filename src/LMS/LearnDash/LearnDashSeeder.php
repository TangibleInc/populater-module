<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LearnDash LMS plugin.
 *
 * Links course steps via LearnDash APIs so nested permalinks and quiz navigation work.
 * Seeds ProQuiz records and topics for a realistic course hierarchy.
 */
class LearnDashSeeder extends AbstractSeeder
{
    protected function afterLessonCreated(int $postId, int $courseId, int $index, array $options = []): void
    {
        if ($courseId > 0) {
            $this->linkStepToCourse($courseId, $postId, $courseId, $this->getPostType('lessons'));
        }

        $options['course_id'] = $courseId;
        $topicIds             = $this->seedTopicsForLesson($postId, $options);

        if ($topicIds !== []) {
            SeedingIdMap::recordQuizParentsFromOptions('topics', $topicIds, $options);
        }
    }

    protected function afterQuizCreated(int $postId, int $parentId, int $index, array $options = []): void
    {
        $title = $this->defaultTitle(
            $options['title_prefix'] ?? $this->getTitlePrefix('quizzes'),
            (int) ($options['index'] ?? $index),
        );
        LearnDashProQuizHelper::createProQuiz($postId, $title);

        $courseId = (int) ($options['course_id'] ?? 0);
        $topicId  = (int) ($options['quiz_parent_id'] ?? $parentId);
        $lessonId = (int) ($options['lesson_id'] ?? 0);

        if ($lessonId <= 0 && $topicId > 0) {
            $lessonId = (int) get_post_meta($topicId, 'lesson_id', true);
        }

        if ($courseId <= 0 && $lessonId > 0) {
            $courseId = (int) get_post_meta($lessonId, 'course_id', true);
        }

        if ($courseId > 0 && $topicId > 0 && $lessonId > 0) {
            $this->linkQuizToTopic($courseId, $postId, $lessonId, $topicId);
        }
    }

    protected function afterQuestionCreated(int $postId, int $quizId, int $index, array $options = []): void
    {
        $title = $this->defaultTitle(
            $options['title_prefix'] ?? $this->getTitlePrefix('questions'),
            $index,
        );
        $content = DummyContent::question($title, $index);

        LearnDashProQuizHelper::attachQuestion($quizId, $postId, $title, $content, $index);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedTopics(int $count, int $lessonId, array $options = []): array
    {
        $courseId = (int) ($options['course_id'] ?? 0);

        if ($courseId <= 0 && $lessonId > 0) {
            $courseId = (int) get_post_meta($lessonId, 'course_id', true);
        }

        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('topics'), $i);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('topics'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::topic($title, $i),
                'post_excerpt' => DummyContent::excerpt('topic', $title, $i),
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('topics', [
                    'courseId' => $courseId,
                    'lessonId' => $lessonId,
                ]));

                if ($courseId > 0 && $lessonId > 0) {
                    $this->linkStepToCourse($courseId, $postId, $lessonId, $this->getPostType('topics'));
                }

                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuizzes(int $count, int $parentId, array $options = []): array
    {
        $topicId  = (int) ($options['quiz_parent_id'] ?? $parentId);
        $lessonId = (int) ($options['lesson_id'] ?? 0);
        $courseId = (int) ($options['course_id'] ?? 0);

        if ($lessonId <= 0 && $topicId > 0) {
            $lessonId = (int) get_post_meta($topicId, 'lesson_id', true);
        }

        if ($courseId <= 0 && $lessonId > 0) {
            $courseId = (int) get_post_meta($lessonId, 'course_id', true);
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
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('quizzes', [
                    'lessonId' => $lessonId,
                    'courseId' => $courseId,
                    'topicId'  => $topicId,
                ]));
                $this->afterQuizCreated($postId, $topicId, $index, array_merge($options, [
                    'course_id'      => $courseId,
                    'lesson_id'      => $lessonId,
                    'quiz_parent_id' => $topicId,
                ]));
                $this->seedQuestionsForQuiz($postId, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    protected function seedTopicsForLesson(int $lessonId, array $options = []): array
    {
        $count = (int) ($options['topics_per_lesson'] ?? 0);

        if ($count <= 0 || $lessonId <= 0) {
            return [];
        }

        return $this->seedTopics($count, $lessonId, $options);
    }

    private function linkQuizToTopic(int $courseId, int $quizId, int $lessonId, int $topicId): void
    {
        if ($courseId <= 0 || $quizId <= 0 || $lessonId <= 0 || $topicId <= 0) {
            return;
        }

        if (function_exists('learndash_course_add_child_to_parent')) {
            learndash_course_add_child_to_parent($courseId, $quizId, $topicId);

            return;
        }

        $this->linkQuizToTopicFallback($courseId, $quizId, $lessonId, $topicId);
    }

    /**
     * Register a step in the course hierarchy using LearnDash APIs when available.
     */
    private function linkStepToCourse(int $courseId, int $childId, int $parentId, string $childType): void
    {
        if ($courseId <= 0 || $childId <= 0 || $parentId <= 0 || $childType === '') {
            return;
        }

        if (function_exists('learndash_course_add_child_to_parent')) {
            learndash_course_add_child_to_parent($courseId, $childId, $parentId);

            return;
        }

        $this->linkStepToCourseFallback($courseId, $childId, $parentId, $childType);
    }

    /**
     * Unit-test fallback when LearnDash step APIs are unavailable.
     */
    private function linkStepToCourseFallback(int $courseId, int $childId, int $parentId, string $childType): void
    {
        $lessonType = $this->getPostType('lessons');
        $topicType  = $this->getPostType('topics');
        $quizType   = $this->getPostType('quizzes');

        if ($childType === $quizType || $childType === $topicType) {
            update_post_meta($childId, 'course_id', $courseId);
        }

        $steps = get_post_meta($courseId, 'ld_course_steps', true);

        if (!is_array($steps)) {
            $steps = [
                'steps'    => ['h' => [$lessonType => []]],
                'versions' => [],
                'empty'    => false,
                'course_id' => $courseId,
            ];
        }

        if (!isset($steps['steps']['h'][$lessonType]) || !is_array($steps['steps']['h'][$lessonType])) {
            $steps['steps']['h'][$lessonType] = [];
        }

        if ($childType === $lessonType && $parentId === $courseId) {
            $steps['steps']['h'][$lessonType][$childId] = [
                $topicType => [],
                $quizType  => [],
            ];
        } elseif ($childType === $topicType) {
            if (!isset($steps['steps']['h'][$lessonType][$parentId])) {
                $steps['steps']['h'][$lessonType][$parentId] = [
                    $topicType => [],
                    $quizType  => [],
                ];
            }

            if (!isset($steps['steps']['h'][$lessonType][$parentId][$topicType]) || !is_array($steps['steps']['h'][$lessonType][$parentId][$topicType])) {
                $steps['steps']['h'][$lessonType][$parentId][$topicType] = [];
            }

            $steps['steps']['h'][$lessonType][$parentId][$topicType][$childId] = [];
        }

        $steps['empty'] = false;
        update_post_meta($courseId, 'ld_course_steps', $steps);
    }

    private function linkQuizToTopicFallback(int $courseId, int $quizId, int $lessonId, int $topicId): void
    {
        update_post_meta($quizId, 'course_id', $courseId);

        $steps = get_post_meta($courseId, 'ld_course_steps', true);

        if (!is_array($steps)) {
            $steps = [
                'steps'    => ['h' => [$this->getPostType('lessons') => []]],
                'versions' => [],
                'empty'    => false,
                'course_id' => $courseId,
            ];
        }

        $lessonType = $this->getPostType('lessons');
        $topicType  = $this->getPostType('topics');
        $quizType   = $this->getPostType('quizzes');

        if (!isset($steps['steps']['h'][$lessonType]) || !is_array($steps['steps']['h'][$lessonType])) {
            $steps['steps']['h'][$lessonType] = [];
        }

        if (!isset($steps['steps']['h'][$lessonType][$lessonId])) {
            $steps['steps']['h'][$lessonType][$lessonId] = [
                $topicType => [],
                $quizType  => [],
            ];
        }

        if (!isset($steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId]) || !is_array($steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId])) {
            $steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId] = [];
        }

        if (!isset($steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId][$quizType]) || !is_array($steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId][$quizType])) {
            $steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId][$quizType] = [];
        }

        $steps['steps']['h'][$lessonType][$lessonId][$topicType][$topicId][$quizType][$quizId] = [];
        $steps['empty'] = false;
        update_post_meta($courseId, 'ld_course_steps', $steps);
    }

    /** @param array<string, mixed> $options */
    protected function assignCourseToGroup(
        int $courseId,
        int $groupId,
        int $groupIndex,
        array $options = [],
    ): void {
        if ($courseId <= 0 || $groupId <= 0) {
            return;
        }

        if (function_exists('ld_update_course_group_access')) {
            ld_update_course_group_access($courseId, $groupId);
        }
    }

    /** @param array<string, mixed> $options */
    protected function assignUserToGroup(
        int $userId,
        int $groupId,
        int $groupIndex,
        bool $isGroupAdmin,
        array $options = [],
    ): void {
        if ($userId <= 0 || $groupId <= 0) {
            return;
        }

        if ($isGroupAdmin) {
            if (function_exists('learndash_set_groups_administrators')) {
                learndash_set_groups_administrators($groupId, [$userId]);
            } elseif (function_exists('ld_update_leader_group_access')) {
                ld_update_leader_group_access($userId, $groupId);
            }

            return;
        }

        if (function_exists('ld_update_group_access')) {
            ld_update_group_access($userId, $groupId);
        }
    }
}
