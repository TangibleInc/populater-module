<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

/**
 * Counts LMS entities in WordPress and verifies LMS-specific structure.
 */
final class LmsContentInspector
{
    /** @return array{courses: int, lessons: int, quizzes: int, sections: int, users: int} */
    public static function snapshot(string $pluginSlug): array
    {
        $types = self::postTypesFor($pluginSlug);

        return [
            'courses'  => self::countPosts($types['courses']),
            'lessons'  => self::countPosts($types['lessons']),
            'quizzes'  => self::countPosts($types['quizzes']),
            'sections' => isset($types['sections']) ? self::countPosts($types['sections']) : 0,
            'users'    => self::countUsers($types['user_prefix']),
        ];
    }

    /**
     * @param array{courses: int, lessons: int, quizzes: int, sections: int, users: int} $before
     * @param array{courses: int, lessons: int, quizzes: int, sections: int, users: int} $after
     * @return array{courses: int, lessons: int, quizzes: int, sections: int, users: int}
     */
    public static function diff(array $before, array $after): array
    {
        return [
            'courses'  => $after['courses'] - $before['courses'],
            'lessons'  => $after['lessons'] - $before['lessons'],
            'quizzes'  => $after['quizzes'] - $before['quizzes'],
            'sections' => $after['sections'] - $before['sections'],
            'users'    => $after['users'] - $before['users'],
        ];
    }

    public static function expectedCounts(int $courses, int $lessonsPerCourse, int $quizzesPerLesson, int $users): array
    {
        $lessons = $courses * $lessonsPerCourse;
        $quizzes = $lessons * $quizzesPerLesson;

        return [
            'courses'  => $courses,
            'lessons'  => $lessons,
            'quizzes'  => $quizzes,
            'sections' => $courses,
            'users'    => $users,
        ];
    }

    /**
     * @return list<\WP_Post>
     */
    public static function newestPosts(string $postType, int $count, int $afterId = 0): array
    {
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'any',
            'posts_per_page' => $count + 20,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ]);

        $filtered = array_values(array_filter(
            $posts,
            static fn(\WP_Post $post) => (int) $post->ID > $afterId,
        ));

        return array_slice($filtered, 0, $count);
    }

    public static function assertLearnDashStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
    ): void {
        $coursePosts = self::newestPosts('sfwd-courses', $courses, $afterPostId);

        $test->assertCount($courses, $coursePosts, 'Expected newly created LearnDash courses.');

        foreach ($coursePosts as $course) {
            $steps = get_post_meta($course->ID, 'ld_course_steps', true);
            $test->assertIsArray($steps, 'Course should have ld_course_steps meta.');
            $lessonSteps = $steps['steps']['h']['sfwd-lessons'] ?? [];
            $test->assertCount($lessonsPerCourse, $lessonSteps, 'Course should contain expected lessons in ld_course_steps.');

            foreach ($lessonSteps as $lessonId => $entry) {
                $test->assertArrayHasKey('sfwd-topic', $entry, 'LearnDash lesson step should reserve sfwd-topic.');
                $test->assertIsArray($entry['sfwd-topic']);
                $test->assertArrayHasKey('sfwd-quiz', $entry);
                $test->assertCount($quizzesPerLesson, $entry['sfwd-quiz'], 'Lesson should contain expected quizzes in ld_course_steps.');
                $test->assertSame($courses > 0 ? $course->ID : 0, (int) get_post_meta((int) $lessonId, 'course_id', true));
            }
        }

        $quizPosts = self::newestPosts(
            'sfwd-quiz',
            $courses * $lessonsPerCourse * $quizzesPerLesson,
            $afterPostId,
        );

        foreach ($quizPosts as $quiz) {
            $proId = (int) get_post_meta($quiz->ID, 'quiz_pro_id', true);
            $test->assertGreaterThan(0, $proId, 'LearnDash quiz should have quiz_pro_id meta.');
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, 'lesson_id', true), 'LearnDash quiz should reference a lesson.');
        }
    }

    public static function assertLifterStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
    ): void {
        $sections = self::newestPosts('llms_section', $courses, $afterPostId);
        $test->assertCount($courses, $sections, 'LifterLMS should create one section per course.');

        foreach ($sections as $section) {
            $test->assertGreaterThan(0, (int) $section->post_parent, 'Section should be attached to a course.');
            $test->assertSame(
                (int) $section->post_parent,
                (int) get_post_meta($section->ID, '_llms_parent_course', true),
            );
        }

        $lessons = self::newestPosts('lesson', $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            $sectionId = (int) get_post_meta($lesson->ID, '_llms_parent_section', true);
            $test->assertGreaterThan(0, $sectionId, 'LifterLMS lesson should belong to a section.');
            $test->assertSame($sectionId, (int) $lesson->post_parent, 'LifterLMS lesson post_parent should be the section.');
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, '_llms_parent_course', true));
        }

        $quizzes = self::newestPosts(
            'llms_quiz',
            $courses * $lessonsPerCourse * $quizzesPerLesson,
            $afterPostId,
        );

        foreach ($quizzes as $quiz) {
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_llms_lesson_id', true), 'LifterLMS quiz should reference a lesson.');
        }
    }

    public static function assertTangibleStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
    ): void {
        $lessons = self::newestPosts('tgl_lesson', $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            $test->assertGreaterThan(0, (int) $lesson->post_parent, 'Tangible lesson should be attached to a course.');
            $test->assertSame(
                (int) $lesson->post_parent,
                (int) get_post_meta($lesson->ID, '_tgl_course_id', true),
            );
        }

        $quizzes = self::newestPosts(
            'tgl_quiz',
            $courses * $lessonsPerCourse * $quizzesPerLesson,
            $afterPostId,
        );

        foreach ($quizzes as $quiz) {
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_tgl_lesson_id', true), 'Tangible quiz should reference a lesson.');
        }
    }

    /** @return array{courses: string, lessons: string, quizzes: string, user_prefix: string, sections?: string} */
    private static function postTypesFor(string $pluginSlug): array
    {
        return match ($pluginSlug) {
            'learndash' => [
                'courses'     => 'sfwd-courses',
                'lessons'     => 'sfwd-lessons',
                'quizzes'     => 'sfwd-quiz',
                'user_prefix' => 'learndash_user',
            ],
            'lifterlms' => [
                'courses'     => 'course',
                'lessons'     => 'lesson',
                'quizzes'     => 'llms_quiz',
                'sections'    => 'llms_section',
                'user_prefix' => 'lifterlms_user',
            ],
            'tangible-lms' => [
                'courses'     => 'tgl_course',
                'lessons'     => 'tgl_lesson',
                'quizzes'     => 'tgl_quiz',
                'user_prefix' => 'tangible_lms_user',
            ],
            default => throw new \InvalidArgumentException(sprintf('Unknown plugin slug "%s".', $pluginSlug)),
        };
    }

    private static function countPosts(string $postType): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
                $postType,
            ),
        );
    }

    private static function countUsers(string $prefix): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->users} WHERE user_login LIKE %s",
                $wpdb->esc_like($prefix . '_') . '%',
            ),
        );
    }
}
