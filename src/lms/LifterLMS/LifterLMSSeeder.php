<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Seeder for the LifterLMS plugin.
 *
 * Post types used by LifterLMS:
 *  - course
 *  - lesson
 *  - llms_quiz
 *  - llms_section (groups lessons inside a course)
 */
class LifterLMSSeeder extends AbstractSeeder
{
    private const PLUGIN_FILE = 'lifterlms/lifterlms.php';

    public function getName(): string
    {
        return 'LifterLMS';
    }

    public function getSlug(): string
    {
        return 'lifterlms';
    }

    public function isActive(): bool
    {
        return is_plugin_active(self::PLUGIN_FILE);
    }

    public function seedCourses(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LifterLMS Course';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'course',
                'post_status'  => 'publish',
                'post_content' => "Sample LifterLMS course $i content.",
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
            $title  = $options['title_prefix'] ?? 'LifterLMS Lesson';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'lesson',
                'post_status'  => 'publish',
                'post_content' => "Sample LifterLMS lesson $i content.",
                'post_parent'  => $courseId,
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, '_llms_parent_course', $courseId);
                $ids[] = $postId;
            }
        }
        return $ids;
    }

    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title  = $options['title_prefix'] ?? 'LifterLMS Quiz';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'llms_quiz',
                'post_status'  => 'publish',
                'post_content' => "Sample LifterLMS quiz $i.",
            ]);

            if (!is_wp_error($postId)) {
                update_post_meta($postId, '_llms_lesson_id', $lessonId);
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
            $username = 'llms_user_' . $unique;
            $email    = 'llms_user_' . $unique . '@example.com';
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
            $title  = $options['title_prefix'] ?? 'LifterLMS Certificate';
            $postId = wp_insert_post([
                'post_title'   => "$title $i",
                'post_type'    => 'llms_certificate',
                'post_status'  => 'publish',
                'post_content' => "Sample LifterLMS certificate $i.",
            ]);

            if (!is_wp_error($postId)) {
                $ids[] = $postId;
            }
        }
        return $ids;
    }
}
