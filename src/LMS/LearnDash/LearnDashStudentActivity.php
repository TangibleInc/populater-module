<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Simulates complete student activity for LearnDash reports testing.
 *
 * API-only strategy
 * =================
 * Every step goes through the LearnDash API exclusively — no direct DB writes.
 * Direct writes bypass the hooks that populate learndash_user_activity and the
 * reporting tables.
 *
 * Completion cascade
 * ==================
 * 1. ld_update_course_access()              → grants course enrollment
 * 2. recordQuizAttempt()                    → writes _sfwd-quizzes user meta
 *                                           → fires learndash_quiz_completed hook
 *                                           → cascade marks topic → lesson complete
 * 3. learndash_process_mark_complete($force)→ marks topics/lessons with no quiz
 * 4. Course completion cascades automatically via learndash_process_mark_complete
 *    when all steps are done.
 *
 * Note: $force = true bypasses video-progression and prerequisite checks that
 * would block completion in a seeding context with no real user sessions.
 */
final class LearnDashStudentActivity
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
            'post_type'      => 'sfwd-courses',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'   => AbstractSeeder::COURSE_META_MARKER,
                'value' => 'learndash',
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    private static function completeCourse(int $userId, int $courseId): bool
    {
        if ($courseId <= 0) {
            return false;
        }

        if (!function_exists('ld_update_course_access') || !function_exists('learndash_process_mark_complete')) {
            return false;
        }

        // Step 1 — Enroll via API
        ld_update_course_access($userId, $courseId);

        // Step 2 — Complete topics (innermost — before lessons)
        foreach (self::getCourseItems($courseId, 'sfwd-topic') as $topicId) {
            $quizIds = self::getChildQuizzes($topicId, $courseId);
            if (!empty($quizIds)) {
                foreach ($quizIds as $quizId) {
                    self::recordQuizAttempt($userId, $quizId, $courseId);
                }
            } else {
                self::forceMarkComplete($userId, $topicId, $courseId);
            }
        }

        // Step 3 — Complete lessons
        foreach (self::getCourseItems($courseId, 'sfwd-lessons') as $lessonId) {
            $quizIds = self::getChildQuizzes($lessonId, $courseId);
            if (!empty($quizIds)) {
                foreach ($quizIds as $quizId) {
                    self::recordQuizAttempt($userId, $quizId, $courseId);
                }
            } else {
                self::forceMarkComplete($userId, $lessonId, $courseId);
            }
        }

        // Step 4 — Complete standalone course-level quizzes (no parent lesson/topic)
        foreach (self::getStandaloneCourseQuizzes($courseId) as $quizId) {
            self::recordQuizAttempt($userId, $quizId, $courseId);
        }

        // Step 5 — Mark course complete (cascades automatically if all steps done,
        // but force it to be safe)
        self::forceMarkComplete($userId, $courseId, $courseId);

        return true;
    }

    /**
     * Write a passing quiz attempt to _sfwd-quizzes user meta and fire
     * learndash_quiz_completed so LD's hooks update activity tables and cascade
     * lesson/topic/course completion.
     */
    private static function recordQuizAttempt(int $userId, int $quizId, int $courseId): void
    {
        if (!function_exists('learndash_get_setting')) {
            return;
        }

        $questionCount = self::getQuizQuestionCount($quizId);
        $proQuizId     = (int) get_post_meta($quizId, 'quiz_pro_id', true);
        $lessonId      = (int) learndash_get_setting($quizId, 'lesson');
        $topicId       = (int) learndash_get_setting($quizId, 'topic');
        $now           = time();

        $attemptData = [
            'quiz'             => $quizId,
            'score'            => $questionCount,
            'count'            => $questionCount,
            'pass'             => 1,
            'rank'             => '-',
            'time'             => $now,
            'points'           => $questionCount,
            'total_points'     => $questionCount,
            'percentage'       => '100.00',
            'statistic_ref_id' => 0,
            'started_at'       => $now,
            'completed_at'     => $now,
            'course'           => $courseId,
            'lesson'           => $lessonId,
            'topic'            => $topicId,
            'pro_quizid'       => $proQuizId,
            'quiz_key'         => 'quiz_' . $quizId . '_' . $now,
        ];

        // Write to _sfwd-quizzes meta (this is what LD reporting reads)
        $existing = get_user_meta($userId, '_sfwd-quizzes', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[] = $attemptData;
        update_user_meta($userId, '_sfwd-quizzes', $existing);

        // Fire the completion hook — LD listeners cascade completion up the tree
        // and write to learndash_user_activity table.
        $user = get_user_by('id', $userId);
        if ($user instanceof \WP_User) {
            do_action('learndash_quiz_completed', $attemptData, $user);
        }
    }

    private static function forceMarkComplete(int $userId, int $postId, int $courseId): void
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return;
        }

        learndash_process_mark_complete($userId, $post, false, $courseId, true);
    }

    /**
     * @return list<int>
     */
    private static function getCourseItems(int $courseId, string $postType): array
    {
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'   => 'course_id',
                'value' => $courseId,
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * Get quizzes whose direct parent (lesson or topic) is the given post ID.
     *
     * @return list<int>
     */
    private static function getChildQuizzes(int $parentId, int $courseId): array
    {
        $posts = get_posts([
            'post_type'      => 'sfwd-quiz',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'course_id',
                    'value' => $courseId,
                ],
                [
                    'relation' => 'OR',
                    ['key' => 'lesson_id', 'value' => $parentId],
                    ['key' => 'topic_id',  'value' => $parentId],
                ],
            ],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * Standalone quizzes that belong to the course but have no lesson/topic parent.
     *
     * @return list<int>
     */
    private static function getStandaloneCourseQuizzes(int $courseId): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_course ON p.ID = pm_course.post_id AND pm_course.meta_key = 'course_id' AND pm_course.meta_value = %d
             LEFT  JOIN {$wpdb->postmeta} pm_lesson ON p.ID = pm_lesson.post_id AND pm_lesson.meta_key = 'lesson_id' AND pm_lesson.meta_value != '0'
             LEFT  JOIN {$wpdb->postmeta} pm_topic  ON p.ID = pm_topic.post_id  AND pm_topic.meta_key  = 'topic_id'  AND pm_topic.meta_value  != '0'
             WHERE p.post_type = 'sfwd-quiz' AND p.post_status = 'publish'
               AND pm_lesson.post_id IS NULL AND pm_topic.post_id IS NULL",
            $courseId,
        ));

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    private static function getQuizQuestionCount(int $quizId): int
    {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'sfwd-question'
               AND pm.meta_key = 'quiz_id'
               AND pm.meta_value = %d",
            $quizId,
        ));

        return max(1, $count);
    }
}
