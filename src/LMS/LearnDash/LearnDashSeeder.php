<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LearnDash LMS plugin.
 *
 * Overrides course/quiz seeding to maintain ld_course_steps and quiz_pro_id meta.
 * Seeds topics inside each lesson for a realistic course hierarchy.
 */
class LearnDashSeeder extends AbstractSeeder
{
    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'sfwd-courses',
            'lessons'      => 'sfwd-lessons',
            'topics'       => 'sfwd-topic',
            'quizzes'      => 'sfwd-quiz',
            'questions'    => 'sfwd-question',
            'certificates' => 'sfwd-certificates',
            default        => 'post',
        };
    }

    protected function getTitlePrefix(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'LearnDash Course',
            'lessons'      => 'LearnDash Lesson',
            'topics'       => 'LearnDash Topic',
            'quizzes'      => 'LearnDash Quiz',
            'questions'    => 'LearnDash Question',
            'certificates' => 'LearnDash Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'topics' => [
                'course_id' => (int) ($context['courseId'] ?? 0),
                'lesson_id' => (int) ($context['lessonId'] ?? 0),
            ],
            default => parent::getMetaFor($entity, $context),
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

        $options['course_id'] = $courseId;
        $this->seedTopicsForLesson($postId, $options);
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

    protected function afterQuestionCreated(int $postId, int $quizId, int $index, array $options = []): void
    {
        $this->assignQuestionProId($postId);
        $this->attachQuestionToQuiz($quizId, $postId);
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
                    $this->addTopicToCourseSteps($courseId, $lessonId, $postId);
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
    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        if (!isset($options['course_id']) && $lessonId > 0) {
            $options['course_id'] = (int) get_post_meta($lessonId, 'course_id', true);
        }

        return parent::seedQuizzes($count, $lessonId, $options);
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

    private function addTopicToCourseSteps(int $courseId, int $lessonId, int $topicId): void
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

        if (!isset($steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-topic']) || !is_array($steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-topic'])) {
            $steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-topic'] = [];
        }

        $steps['steps']['h']['sfwd-lessons'][$lessonId]['sfwd-topic'][$topicId] = [];

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

    private function assignQuestionProId(int $questionPostId): void
    {
        $proId = (int) get_post_meta($questionPostId, 'question_pro_id', true);

        if ($proId <= 0) {
            update_post_meta($questionPostId, 'question_pro_id', $questionPostId);
        }

        update_post_meta($questionPostId, 'question_type', 'single');
    }

    private function attachQuestionToQuiz(int $quizId, int $questionId): void
    {
        $questions = get_post_meta($quizId, 'ld_quiz_questions', true);

        if (!is_array($questions)) {
            $questions = [];
        }

        $questions[$questionId] = $questionId;

        update_post_meta($quizId, 'ld_quiz_questions', $questions);
    }
}
