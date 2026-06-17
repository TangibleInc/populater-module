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
 * 2. For each topic: recordQuizAttempt() (if it has a quiz), then
 *    forceMarkComplete(topic) — explicit force is required because hook-based
 *    cascades are unreliable in a headless background context.
 *    recordQuizAttempt() calls learndash_update_user_activity() + writes
 *    _sfwd-quizzes meta + fires learndash_quiz_completed, matching LD's own
 *    quiz-submission pipeline in ld-quiz-pro.php.
 * 3. For each lesson: recordQuizAttempt() for any direct-child quizzes (quizzes
 *    with no topic_id), then forceMarkComplete(lesson). Topic-nested quizzes are
 *    skipped here to avoid duplicate attempts; those were already handled in step 2.
 * 4. forceMarkComplete(course) — ensures 100% even if cascade did not fire.
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
            foreach (self::getChildQuizzes($topicId, $courseId) as $quizId) {
                self::recordQuizAttempt($userId, $quizId, $courseId);
            }
            // Always force-mark the topic complete; quiz-completion hooks may not
            // cascade reliably in a background/headless context.
            self::forceMarkComplete($userId, $topicId, $courseId);
        }

        // Step 3 — Complete lessons
        foreach (self::getCourseItems($courseId, 'sfwd-lessons') as $lessonId) {
            foreach (self::getDirectLessonQuizzes($lessonId, $courseId) as $quizId) {
                self::recordQuizAttempt($userId, $quizId, $courseId);
            }
            // Always force-mark the lesson complete. When quizzes are nested under
            // topics, getChildQuizzes() would find them via lesson_id and skip the
            // forceMarkComplete call — leaving the lesson at 0% even though all
            // topics were finished in Step 2.
            self::forceMarkComplete($userId, $lessonId, $courseId);
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
     * Record a passing quiz attempt using LD's official APIs.
     *
     * Mirrors what LD does in ld-quiz-pro.php when a browser quiz submission
     * arrives:
     *   1. learndash_update_user_activity() → writes to learndash_user_activity table
     *   2. update_user_meta(_sfwd-quizzes)  → canonical per-user quiz history storage
     *   3. learndash_quiz_completed hook    → notifies certificates and other integrations
     *
     * The 'started'/'completed' keys (not 'started_at'/'completed_at') and the
     * canonical quiz_key format are required; LD silently skips activity recording
     * when they are absent or malformed.
     */
    private static function recordQuizAttempt(int $userId, int $quizId, int $courseId): void
    {
        if (!function_exists('learndash_get_setting') || !function_exists('learndash_update_user_activity')) {
            return;
        }

        $questionCount = self::getQuizQuestionCount($quizId);
        $proQuizId     = (int) get_post_meta($quizId, 'quiz_pro_id', true);
        $lessonId      = (int) learndash_get_setting($quizId, 'lesson');
        $topicId       = (int) learndash_get_setting($quizId, 'topic');
        $completed     = time();
        $started       = $completed - 30;

        $attemptData = [
            'quiz'             => $quizId,
            'score'            => $questionCount,
            'count'            => $questionCount,
            'pass'             => 1,
            'rank'             => '-',
            'time'             => $completed,
            'points'           => $questionCount,
            'total_points'     => $questionCount,
            'percentage'       => 100,
            'statistic_ref_id' => 0,
            'started'          => $started,
            'completed'        => $completed,
            'timespent'        => 30,
            'has_graded'       => false,
            'course'           => $courseId,
            'lesson'           => $lessonId,
            'topic'            => $topicId,
            'pro_quizid'       => $proQuizId,
            'quiz_key'         => $completed . '_' . $proQuizId . '_' . $quizId . '_' . $courseId,
            'ld_version'       => defined('LEARNDASH_VERSION') ? LEARNDASH_VERSION : '',
        ];

        // Step 1 — record in learndash_user_activity table (LD's reporting source).
        // Must come before the meta write, matching LD's own processing order.
        learndash_update_user_activity([
            'course_id'          => $courseId,
            'user_id'            => $userId,
            'post_id'            => $quizId,
            'activity_type'      => 'quiz',
            'activity_status'    => true,
            'activity_started'   => $started,
            'activity_completed' => $completed,
            'activity_meta'      => $attemptData,
        ]);

        // Step 2 — append to _sfwd-quizzes user meta (canonical quiz history storage).
        $existing = get_user_meta($userId, '_sfwd-quizzes', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[] = $attemptData;
        update_user_meta($userId, '_sfwd-quizzes', $existing);

        // Step 3 — fire learndash_quiz_completed for certificates and third-party
        // integrations. LD passes WP_Post objects for course/lesson/topic in this hook.
        $user = get_user_by('id', $userId);
        if ($user instanceof \WP_User) {
            do_action('learndash_quiz_completed', array_merge($attemptData, [
                'course' => get_post($courseId) ?: $courseId,
                'lesson' => $lessonId > 0 ? (get_post($lessonId) ?: $lessonId) : 0,
                'topic'  => $topicId > 0 ? (get_post($topicId) ?: $topicId) : 0,
            ]), $user);
        }
    }

    private static function forceMarkComplete(int $userId, int $postId, int $courseId): void
    {
        if (!get_post($postId) instanceof \WP_Post) {
            return;
        }

        // Pass the integer $postId, NOT a WP_Post object. learndash_process_mark_complete's
        // second parameter is $postid (int) — it uses it as an array key internally, so
        // passing an object causes "Illegal offset type" in ld-course-progress.php.
        learndash_process_mark_complete($userId, $postId, false, $courseId, true);
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
     * Quizzes attached directly to a lesson (no topic parent).
     *
     * Excludes quizzes that have a topic_id set, which belong to a topic nested
     * inside the lesson and are already handled in the topic-completion pass.
     *
     * @return list<int>
     */
    private static function getDirectLessonQuizzes(int $lessonId, int $courseId): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_course  ON p.ID = pm_course.post_id  AND pm_course.meta_key  = 'course_id'  AND pm_course.meta_value  = %d
             INNER JOIN {$wpdb->postmeta} pm_lesson  ON p.ID = pm_lesson.post_id  AND pm_lesson.meta_key  = 'lesson_id'  AND pm_lesson.meta_value  = %d
             LEFT  JOIN {$wpdb->postmeta} pm_topic   ON p.ID = pm_topic.post_id   AND pm_topic.meta_key   = 'topic_id'   AND pm_topic.meta_value  != '0'
             WHERE p.post_type = 'sfwd-quiz' AND p.post_status = 'publish'
               AND pm_topic.post_id IS NULL",
            $courseId,
            $lessonId,
        ));

        return is_array($ids) ? array_map('intval', $ids) : [];
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
