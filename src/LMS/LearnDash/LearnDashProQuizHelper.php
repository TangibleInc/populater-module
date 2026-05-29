<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

/**
 * Creates LearnDash ProQuiz records for seeded quiz and question posts.
 *
 * LearnDash quizzes require rows in wp_pro_quiz_* tables and linked post meta.
 * Without them the admin shortcode column shows "Missing ProQuiz Associated Settings."
 */
final class LearnDashProQuizHelper
{
    public static function isAvailable(): bool
    {
        return class_exists(\WpProQuiz_Model_QuizMapper::class)
            && function_exists('learndash_update_setting')
            && function_exists('learndash_update_pro_question')
            && function_exists('learndash_proquiz_sync_question_fields');
    }

    public static function createProQuiz(int $quizPostId, string $title): int
    {
        if (!self::isAvailable()) {
            return self::assignPlaceholderQuizProId($quizPostId);
        }

        $existing = (int) get_post_meta($quizPostId, 'quiz_pro_id', true);

        if ($existing > 0) {
            $mapper = new \WpProQuiz_Model_QuizMapper();

            if ((int) $mapper->exists($existing) > 0) {
                return $existing;
            }
        }

        $quiz = new \WpProQuiz_Model_Quiz();
        $quiz->setName($title);
        $quiz->setText('AAZZAAZZ');
        $quiz->setPostId($quizPostId);

        $mapper = new \WpProQuiz_Model_QuizMapper();
        $mapper->save($quiz);

        $proId = (int) $quiz->getId();

        if ($proId <= 0) {
            return self::assignPlaceholderQuizProId($quizPostId);
        }

        learndash_update_setting($quizPostId, 'quiz_pro', $proId);

        return $proId;
    }

    public static function attachQuestion(
        int $quizPostId,
        int $questionPostId,
        string $title,
        string $content,
        int $index,
    ): int {
        if (!self::isAvailable()) {
            return self::assignPlaceholderQuestion($quizPostId, $questionPostId);
        }

        $questionType = function_exists('learndash_get_post_type_slug')
            ? (string) learndash_get_post_type_slug('question')
            : 'sfwd-question';

        $questionText = wp_strip_all_tags($content) ?: $title;

        $questionProId = learndash_update_pro_question(
            0,
            [
                'action'       => 'new_step',
                'post_type'    => $questionType,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $questionText,
            ],
        );

        if (empty($questionProId)) {
            return self::assignPlaceholderQuestion($quizPostId, $questionPostId);
        }

        $questionProId = (int) $questionProId;
        $quizProId     = (int) learndash_get_setting($quizPostId, 'quiz_pro');

        $questionMapper = new \WpProQuiz_Model_QuestionMapper();
        $questionModel  = $questionMapper->fetch($questionProId);

        $questionModel->set_array_to_object([
            '_answerData' => self::singleChoiceAnswers($index),
            '_answerType' => 'single',
            '_question'   => $questionText,
        ]);

        if ($quizProId > 0) {
            $questionModel->setQuizId($quizProId);
        }

        $questionMapper->save($questionModel);
        $questionProId = (int) $questionModel->getId();

        self::linkQuestionToQuiz($quizPostId, $questionPostId, $questionProId);

        return $questionProId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function singleChoiceAnswers(int $index): array
    {
        $default = [
            '_html'               => false,
            '_graded'             => '1',
            '_gradedType'         => 'text',
            '_gradingProgression' => 'not-graded-none',
            '_points'             => 1,
            '_sortString'         => '',
            '_sortStringHtml'     => false,
            '_type'               => 'answer',
        ];

        return [
            array_merge($default, [
                '_answer'  => sprintf('Correct answer for question %d', $index),
                '_correct' => true,
            ]),
            array_merge($default, [
                '_answer'  => sprintf('Incorrect option A for question %d', $index),
                '_correct' => false,
                '_points'  => 0,
            ]),
            array_merge($default, [
                '_answer'  => sprintf('Incorrect option B for question %d', $index),
                '_correct' => false,
                '_points'  => 0,
            ]),
        ];
    }

    private static function linkQuestionToQuiz(
        int $quizPostId,
        int $questionPostId,
        int $questionProId,
    ): void {
        $questions = get_post_meta($quizPostId, 'ld_quiz_questions', true);
        $questions = is_array($questions) ? $questions : [];
        $questions[$questionPostId] = $questionProId;

        update_post_meta($quizPostId, 'ld_quiz_questions', $questions);
        update_post_meta($questionPostId, 'question_pro_id', $questionProId);
        learndash_proquiz_sync_question_fields($questionPostId, $questionProId);
        learndash_update_setting($questionPostId, 'quiz', $quizPostId);
        update_post_meta($questionPostId, 'quiz_id', $quizPostId);
    }

    private static function assignPlaceholderQuizProId(int $quizPostId): int
    {
        $proId = (int) get_post_meta($quizPostId, 'quiz_pro_id', true);

        if ($proId <= 0) {
            $proId = $quizPostId;
            update_post_meta($quizPostId, 'quiz_pro_id', $proId);
        }

        return $proId;
    }

    private static function assignPlaceholderQuestion(int $quizPostId, int $questionPostId): int
    {
        $proId = (int) get_post_meta($questionPostId, 'question_pro_id', true);

        if ($proId <= 0) {
            $proId = $questionPostId;
            update_post_meta($questionPostId, 'question_pro_id', $proId);
        }

        update_post_meta($questionPostId, 'question_type', 'single');

        $questions = get_post_meta($quizPostId, 'ld_quiz_questions', true);

        if (!is_array($questions)) {
            $questions = [];
        }

        $questions[$questionPostId] = $questionPostId;
        update_post_meta($quizPostId, 'ld_quiz_questions', $questions);

        return $proId;
    }
}
