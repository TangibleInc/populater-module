<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeders;

/**
 * Seeder for the Tangible LMS plugin.
 *
 * Post types are assumed to follow Tangible LMS conventions.
 * Adjust post type slugs once the plugin's public API is stable.
 */
class TangibleLMSSeeder extends AbstractSeeder
{
    private const PLUGIN_FILE = 'tangible-lms/tangible-lms.php';

    // Post type slugs — update these to match the actual plugin's registered types.
    private const PT_COURSE = 'tgl_course';
    private const PT_LESSON = 'tgl_lesson';
    private const PT_QUIZ   = 'tgl_quiz';

    public function getName(): string
    {
        return 'Tangible LMS';
    }

    public function getSlug(): string
    {
        return 'tangible-lms';
    }

    public function isActive(): bool
    {
        return is_plugin_active(self::PLUGIN_FILE);
    }

    public function seedCourses(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'Tangible Course';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => self::PT_COURSE,
                'post_status'  => 'publish',
                'post_content' => "Sample Tangible LMS course $i content.",
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
            $title  = $options['title_prefix'] ?? 'Tangible Lesson';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => self::PT_LESSON,
                'post_status'  => 'publish',
                'post_content' => "Sample Tangible LMS lesson $i content.",
                'post_parent'  => $courseId,
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, '_tgl_course_id', $courseId);
                $ids[] = $postId;
            }
        }
        return $ids;
    }

    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'Tangible Quiz';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => self::PT_QUIZ,
                'post_status'  => 'publish',
                'post_content' => "Sample Tangible LMS quiz $i.",
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, '_tgl_lesson_id', $lessonId);
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
            $username = 'tgl_user_' . $unique;
            $email    = 'tgl_user_' . $unique . '@example.com';
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
            $title  = $options['title_prefix'] ?? 'Tangible Certificate';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'tgl_certificate',
                'post_status'  => 'publish',
                'post_content' => "Sample Tangible LMS certificate $i.",
            ]);

            if (!is_wp_error($postId)) {
                $ids[] = $postId;
            }
        }
        return $ids;
    }
}
