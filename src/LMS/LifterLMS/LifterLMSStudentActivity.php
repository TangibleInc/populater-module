<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Simulates complete student activity for LifterLMS reports testing.
 *
 * Phase ordering guarantee
 * ========================
 * This class is only called from student_activity queue items, which are
 * appended AFTER all course and user items in AbstractSeeder::buildSeedQueue().
 * By the time any method here executes every seeded course and every seeded
 * student WP user already exists in the database.
 *
 * API-only strategy
 * =================
 * Every step goes through the LifterLMS API exclusively — no direct DB writes.
 * Direct writes break the cascade hooks that populate lifterlms_events and the
 * reporting tables, and they write wrong enrollment status values ('completed'
 * instead of the valid 'enrolled'/'cancelled'/'expired' set).
 *
 * Completion cascade
 * ==================
 * 1. llms_enroll_student()          → writes _status=enrolled + _enrollment_trigger
 * 2. LLMS_Quiz_Attempt::end()       → fires lifterlms_quiz_completed/passed
 * 3. lifterlms_quiz_passed          → LLMS_Controller_Lesson_Progression::quiz_complete()
 *                                   → fires llms_trigger_lesson_completion
 *                                   → auto-marks the lesson complete
 * 4. lesson mark_complete cascade   → auto-marks section → auto-marks course
 * 5. Lessons without a quiz         → $student->mark_complete($id, 'lesson') directly
 */
final class LifterLMSStudentActivity
{
    /**
     * Run the full completion flow for one student across all seeded courses.
     *
     * @return list<int>  Course IDs completed
     */
    public static function completeAllCourses(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $courseIds = self::getSeededCourseIds();
        $completed = [];

        foreach ($courseIds as $courseId) {
            if (self::completeCourse($userId, $courseId)) {
                $completed[] = $courseId;
            }
        }

        return $completed;
    }

    /**
     * @return list<int>
     */
    private static function getSeededCourseIds(): array
    {
        $posts = get_posts([
            'post_type'      => 'course',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'   => AbstractSeeder::COURSE_META_MARKER,
                'value' => 'lifterlms',
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    private static function completeCourse(int $userId, int $courseId): bool
    {
        if ($courseId <= 0) {
            return false;
        }

        // Step 1 — Enroll via API (writes _status=enrolled + _enrollment_trigger)
        if (!function_exists('llms_enroll_student') || !function_exists('llms_get_student')) {
            return false;
        }

        if (!llms_is_user_enrolled($userId, $courseId)) {
            llms_enroll_student($userId, $courseId, 'seeder');
        }

        $student = llms_get_student($userId);
        if (!$student instanceof \LLMS_Student) {
            return false;
        }

        // Step 2 — Work through each lesson; for quiz-gated lessons the
        // quiz->end() fires hooks that cascade into lesson completion
        // automatically. Lessons without a quiz need an explicit mark_complete.
        foreach (self::getCourseLessons($courseId) as $lessonId) {
            self::completeLessonOrQuiz($student, $userId, $lessonId);
        }

        return true;
    }

    private static function completeLessonOrQuiz(
        \LLMS_Student $student,
        int $userId,
        int $lessonId,
    ): void {
        $lesson = function_exists('llms_get_post') ? llms_get_post($lessonId) : null;
        if (!$lesson instanceof \LLMS_Lesson) {
            return;
        }

        $quizId = $lesson->is_quiz_enabled() ? (int) $lesson->get('quiz') : 0;

        if ($quizId > 0) {
            // Submitting a passing quiz attempt fires lifterlms_quiz_passed →
            // LLMS_Controller_Lesson_Progression::quiz_complete() →
            // llms_trigger_lesson_completion → lesson auto-marked complete →
            // section auto-marked → course auto-marked when all lessons done.
            self::submitPassingQuizAttempt($userId, $quizId, $lessonId);
        } else {
            // No quiz on this lesson — mark it complete directly.
            if (!$student->is_complete($lessonId, 'lesson')) {
                $student->mark_complete($lessonId, 'lesson');
            }
        }
    }

    /**
     * Creates a passing quiz attempt using the LifterLMS API.
     *
     * The correct-choice IDs for each question are obtained from
     * LLMS_Quiz_Attempt_Question::get_question() → LLMS_Question::get_correct_choice(),
     * so there is no need to guess or hard-code question formats.
     */
    private static function submitPassingQuizAttempt(int $userId, int $quizId, int $lessonId): void
    {
        try {
            $attempt = \LLMS_Quiz_Attempt::init($quizId, $lessonId, $userId);
        } catch (\Exception $e) {
            return;
        }

        $attempt->start();

        foreach ($attempt->get_question_objects() as $aq) {
            $question = $aq->get_question();
            if (!$question instanceof \LLMS_Question) {
                continue;
            }

            $correct = $question->get_correct_choice();
            $attempt->answer_question($aq->get('id'), is_array($correct) ? $correct : []);
        }

        // end() calculates grade, saves, fires lifterlms_quiz_completed/passed
        $attempt->end();
    }

    /**
     * @return list<int>
     */
    private static function getCourseLessons(int $courseId): array
    {
        $posts = get_posts([
            'post_type'      => 'lesson',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'meta_query'     => [[
                'key'   => '_llms_parent_course',
                'value' => $courseId,
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }
}
