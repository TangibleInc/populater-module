<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Seeder for the LearnDash LMS plugin.
 *
 * Post types used by LearnDash:
 *  - sfwd-courses
 *  - sfwd-lessons
 *  - sfwd-quiz
 *  - sfwd-topic
 */
class LearnDashSeeder extends AbstractSeeder
{
    private const PLUGIN_FILE = 'sfwd-lms/sfwd_lms.php';

    public function getName(): string
    {
        return 'LearnDash LMS';
    }

    public function getSlug(): string
    {
        return 'learndash';
    }

    public function isActive(): bool
    {
        return is_plugin_active(self::PLUGIN_FILE);
    }

    public function seedCourses(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LearnDash Course';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'sfwd-courses',
                'post_status'  => 'publish',
                'post_content' => "Sample course $i content.",
            ]);

            if (!is_wp_error($postId)) {
                $ids[] = $postId;
            }
        }
        return $ids;
    }

    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LearnDash Lesson';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'sfwd-lessons',
                'post_status'  => 'publish',
                'post_content' => "Sample lesson $i content.",
                'post_parent'  => $courseId,
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, 'course_id', $courseId);
                $ids[] = $postId;
            }
        }
        return $ids;
    }

    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LearnDash Quiz';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'sfwd-quiz',
                'post_status'  => 'publish',
                'post_content' => "Sample quiz $i.",
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, 'lesson_id', $lessonId);
                $ids[] = $postId;
            }
        }
        return $ids;
    }

    public function seedUsers(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $unique   = uniqid((string) $i, true);
            $username = 'ld_user_' . $unique;
            $email    = 'ld_user_' . $unique . '@example.com';
            $password = wp_generate_password();

            $userId = wp_create_user($username, $password, $email);

            if (!is_wp_error($userId)) {
                $ids[] = $userId;
            }
        }
        return $ids;
    }

    public function seedCertificates(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LearnDash Certificate';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'sfwd-certificates',
                'post_status'  => 'publish',
                'post_content' => "Sample certificate $i.",
            ]);

            if (!is_wp_error($postId)) {
                $ids[] = $postId;
            }
        }
        return $ids;
    }
}
