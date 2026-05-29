<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

/**
 * Counts LMS entities in WordPress and verifies LMS-specific structure.
 */
final class LmsContentInspector
{
    private const POPULATOR_MARKER = 'Tangible Populator';

    /**
     * Asserts entity count deltas match the seed configuration for any LMS plugin.
     *
     * @param array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} $delta
     * @param array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} $expected
     */
    public static function assertSeededCounts(
        \PHPUnit\Framework\TestCase $test,
        string $pluginSlug,
        array $delta,
        array $expected,
    ): void {
        $test->assertSame($expected['courses'], $delta['courses'], 'Course count mismatch.');
        $test->assertSame($expected['lessons'], $delta['lessons'], 'Lesson count mismatch.');
        $test->assertSame($expected['quizzes'], $delta['quizzes'], 'Quiz count mismatch.');
        $test->assertSame($expected['questions'], $delta['questions'], 'Question count mismatch.');
        $test->assertSame($expected['users'], $delta['users'], 'User count mismatch.');

        if ($pluginSlug === 'learndash') {
            $test->assertSame($expected['topics'], $delta['topics'], 'LearnDash topic count mismatch.');
        }

        if ($pluginSlug === 'lifterlms') {
            $test->assertSame($expected['sections'], $delta['sections'], 'LifterLMS section count mismatch.');
        }

        if ($pluginSlug === 'tangible-lms') {
            $test->assertSame($expected['modules'], $delta['modules'], 'Tangible module count mismatch.');
        }
    }

    /**
     * Runs LMS-specific structural and content assertions after seeding.
     */
    public static function assertSeededStructure(
        \PHPUnit\Framework\TestCase $test,
        string $pluginSlug,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $topicsPerLesson = 2,
        int $sectionsPerCourse = 1,
        int $modulesPerCourse = 1,
    ): void {
        match ($pluginSlug) {
            'learndash' => self::assertLearnDashStructure(
                $test,
                $courses,
                $lessonsPerCourse,
                $quizzesPerLesson,
                $afterPostId,
                $questionsPerQuiz,
                $topicsPerLesson,
            ),
            'lifterlms' => self::assertLifterStructure(
                $test,
                $courses,
                $lessonsPerCourse,
                $quizzesPerLesson,
                $afterPostId,
                $questionsPerQuiz,
                $sectionsPerCourse,
            ),
            'tangible-lms' => self::assertTangibleStructure(
                $test,
                $courses,
                $lessonsPerCourse,
                $quizzesPerLesson,
                $afterPostId,
                $questionsPerQuiz,
                $modulesPerCourse,
            ),
            default => throw new \InvalidArgumentException(sprintf('Unknown plugin slug "%s".', $pluginSlug)),
        };
    }

    /** @return array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} */
    public static function snapshot(string $pluginSlug): array
    {
        $types = self::postTypesFor($pluginSlug);

        return [
            'courses'   => self::countPosts($types['courses']),
            'lessons'   => self::countPosts($types['lessons']),
            'topics'    => isset($types['topics']) ? self::countPosts($types['topics']) : 0,
            'quizzes'   => self::countPosts($types['quizzes']),
            'questions' => self::countPosts($types['questions']),
            'sections'  => isset($types['sections']) ? self::countPosts($types['sections']) : 0,
            'modules'   => isset($types['modules']) ? self::countPosts($types['modules']) : 0,
            'users'     => self::countUsers($types['user_prefix']),
        ];
    }

    /**
     * @param array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} $before
     * @param array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} $after
     * @return array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int}
     */
    public static function diff(array $before, array $after): array
    {
        return [
            'courses'   => $after['courses'] - $before['courses'],
            'lessons'   => $after['lessons'] - $before['lessons'],
            'topics'    => $after['topics'] - $before['topics'],
            'quizzes'   => $after['quizzes'] - $before['quizzes'],
            'questions' => $after['questions'] - $before['questions'],
            'sections'  => $after['sections'] - $before['sections'],
            'modules'   => $after['modules'] - $before['modules'],
            'users'     => $after['users'] - $before['users'],
        ];
    }

    public static function expectedCounts(
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $users,
        int $questionsPerQuiz = 3,
        int $topicsPerLesson = 2,
        int $sectionsPerCourse = 1,
        int $modulesPerCourse = 1,
    ): array {
        $lessons   = $courses * $lessonsPerCourse;
        $quizzes   = $lessons * $quizzesPerLesson;
        $questions = $quizzes * $questionsPerQuiz;
        $topics    = $lessons * $topicsPerLesson;

        return [
            'courses'   => $courses,
            'lessons'   => $lessons,
            'topics'    => $topics,
            'quizzes'   => $quizzes,
            'questions' => $questions,
            'sections'  => $courses * $sectionsPerCourse,
            'modules'   => $courses * $modulesPerCourse,
            'users'     => $users,
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
        int $questionsPerQuiz = 3,
        int $topicsPerLesson = 2,
    ): void {
        $coursePosts = self::newestPosts('sfwd-courses', $courses, $afterPostId);

        $test->assertCount($courses, $coursePosts, 'Expected newly created LearnDash courses.');

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);

            $steps = get_post_meta($course->ID, 'ld_course_steps', true);
            $test->assertIsArray($steps, 'Course should have ld_course_steps meta.');
            $lessonSteps = $steps['steps']['h']['sfwd-lessons'] ?? [];
            $test->assertCount($lessonsPerCourse, $lessonSteps, 'Course should contain expected lessons in ld_course_steps.');

            foreach ($lessonSteps as $lessonId => $entry) {
                $test->assertArrayHasKey('sfwd-topic', $entry, 'LearnDash lesson step should reserve sfwd-topic.');
                $test->assertIsArray($entry['sfwd-topic']);
                $test->assertCount($topicsPerLesson, $entry['sfwd-topic'], 'Lesson should contain expected topics in ld_course_steps.');
                $test->assertArrayHasKey('sfwd-quiz', $entry);
                $test->assertCount($quizzesPerLesson, $entry['sfwd-quiz'], 'Lesson should contain expected quizzes in ld_course_steps.');
                $test->assertSame($courses > 0 ? $course->ID : 0, (int) get_post_meta((int) $lessonId, 'course_id', true));
            }
        }

        $lessonPosts = self::newestPosts('sfwd-lessons', $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessonPosts as $lesson) {
            self::assertRichLessonContent($test, $lesson);
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, 'course_id', true), 'LearnDash lesson should reference a course.');
        }

        if ($topicsPerLesson > 0) {
            $topics = self::newestPosts(
                'sfwd-topic',
                $courses * $lessonsPerCourse * $topicsPerLesson,
                $afterPostId,
            );

            foreach ($topics as $topic) {
                self::assertRichTopicContent($test, $topic);
                $test->assertGreaterThan(0, (int) get_post_meta($topic->ID, 'course_id', true), 'LearnDash topic should reference a course.');
                $test->assertGreaterThan(0, (int) get_post_meta($topic->ID, 'lesson_id', true), 'LearnDash topic should reference a lesson.');
            }
        }

        $quizPosts = self::newestPosts(
            'sfwd-quiz',
            $courses * $lessonsPerCourse * $quizzesPerLesson,
            $afterPostId,
        );

        foreach ($quizPosts as $quiz) {
            self::assertRichQuizContent($test, $quiz);
            $proId = (int) get_post_meta($quiz->ID, 'quiz_pro_id', true);
            $test->assertGreaterThan(0, $proId, 'LearnDash quiz should have quiz_pro_id meta.');
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, 'lesson_id', true), 'LearnDash quiz should reference a lesson.');

            if ($questionsPerQuiz > 0) {
                $questionIds = get_post_meta($quiz->ID, 'ld_quiz_questions', true);
                $test->assertIsArray($questionIds, 'LearnDash quiz should have ld_quiz_questions meta.');
                $test->assertCount($questionsPerQuiz, $questionIds, 'LearnDash quiz should contain expected questions.');

                foreach (array_keys($questionIds) as $questionId) {
                    $test->assertSame(
                        $quiz->ID,
                        (int) get_post_meta((int) $questionId, 'quiz_id', true),
                        'LearnDash question should reference its parent quiz.',
                    );
                }
            }
        }

        if ($questionsPerQuiz > 0) {
            $questionPosts = self::newestPosts(
                'sfwd-question',
                $courses * $lessonsPerCourse * $quizzesPerLesson * $questionsPerQuiz,
                $afterPostId,
            );

            foreach ($questionPosts as $question) {
                self::assertRichQuestionContent($test, $question);
                $test->assertGreaterThan(0, (int) get_post_meta($question->ID, 'quiz_id', true), 'LearnDash question should reference a quiz.');
                $test->assertGreaterThan(0, (int) get_post_meta($question->ID, 'question_pro_id', true), 'LearnDash question should have question_pro_id meta.');
                $test->assertSame('single', get_post_meta($question->ID, 'question_type', true));
            }
        }
    }

    public static function assertLifterStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $sectionsPerCourse = 1,
    ): void {
        $coursePosts = self::newestPosts('course', $courses, $afterPostId);

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);
        }

        $sections = self::newestPosts('llms_section', $courses * $sectionsPerCourse, $afterPostId);
        $test->assertCount($courses * $sectionsPerCourse, $sections, 'LifterLMS should create expected sections per course.');

        foreach ($sections as $section) {
            $test->assertGreaterThan(0, (int) $section->post_parent, 'Section should be attached to a course.');
            self::assertRichSectionContent($test, $section);
            $test->assertSame(
                (int) $section->post_parent,
                (int) get_post_meta($section->ID, '_llms_parent_course', true),
            );
        }

        $lessons = self::newestPosts('lesson', $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            self::assertRichLessonContent($test, $lesson);
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
            self::assertRichQuizContent($test, $quiz);
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_llms_lesson_id', true), 'LifterLMS quiz should reference a lesson.');
        }

        if ($questionsPerQuiz > 0) {
            $questions = self::newestPosts(
                'llms_question',
                $courses * $lessonsPerCourse * $quizzesPerLesson * $questionsPerQuiz,
                $afterPostId,
            );

            foreach ($questions as $question) {
                self::assertRichQuestionContent($test, $question);
                $quizId = (int) get_post_meta($question->ID, '_llms_parent_id', true);
                $test->assertGreaterThan(0, $quizId, 'LifterLMS question should reference a quiz.');
                $test->assertSame('true_false', get_post_meta($question->ID, '_llms_question_type', true));
                $test->assertSame('llms_quiz', get_post_type($quizId), 'LifterLMS question should belong to a quiz post.');
            }
        }
    }

    public static function assertTangibleStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerLesson,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $modulesPerCourse = 1,
    ): void {
        $coursePosts = self::newestPosts('tgl_course', $courses, $afterPostId);

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);
        }

        $modules = self::newestPosts('tgl_module', $courses * $modulesPerCourse, $afterPostId);
        $test->assertCount($courses * $modulesPerCourse, $modules, 'Tangible LMS should create expected modules per course.');

        foreach ($modules as $module) {
            $test->assertGreaterThan(0, (int) $module->post_parent, 'Module should be attached to a course.');
            self::assertRichModuleContent($test, $module);
            $test->assertSame(
                (int) $module->post_parent,
                (int) get_post_meta($module->ID, '_tgl_course_id', true),
            );
        }

        $lessons = self::newestPosts('tgl_lesson', $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            self::assertRichLessonContent($test, $lesson);
            $moduleId = (int) get_post_meta($lesson->ID, '_tgl_module_id', true);
            $test->assertGreaterThan(0, $moduleId, 'Tangible lesson should belong to a module.');
            $test->assertSame($moduleId, (int) $lesson->post_parent, 'Tangible lesson post_parent should be the module.');
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, '_tgl_course_id', true));
        }

        $quizzes = self::newestPosts(
            'tgl_quiz',
            $courses * $lessonsPerCourse * $quizzesPerLesson,
            $afterPostId,
        );

        foreach ($quizzes as $quiz) {
            self::assertRichQuizContent($test, $quiz);
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_tgl_lesson_id', true), 'Tangible quiz should reference a lesson.');
        }

        if ($questionsPerQuiz > 0) {
            $questions = self::newestPosts(
                'tgl_question',
                $courses * $lessonsPerCourse * $quizzesPerLesson * $questionsPerQuiz,
                $afterPostId,
            );

            foreach ($questions as $question) {
                self::assertRichQuestionContent($test, $question);
                $quizId = (int) get_post_meta($question->ID, '_tgl_quiz_id', true);
                $test->assertGreaterThan(0, $quizId, 'Tangible question should reference a quiz.');
                $test->assertSame('tgl_quiz', get_post_type($quizId), 'Tangible question should belong to a quiz post.');
            }
        }
    }

    private static function assertRichCourseContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Course should include a heading in body content.');
        $test->assertStringContainsString(self::POPULATOR_MARKER, (string) $post->post_excerpt, 'Course excerpt should identify Populator content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Course should have body content.');
    }

    private static function assertRichLessonContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Lesson should include a heading in body content.');
        $test->assertStringContainsString('Lesson outline', strip_tags((string) $post->post_content), 'Lesson should include outline content.');
        $test->assertStringContainsString(self::POPULATOR_MARKER, (string) $post->post_excerpt, 'Lesson excerpt should identify Populator content.');
    }

    private static function assertRichTopicContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Topic should include a heading in body content.');
        $test->assertStringContainsString(self::POPULATOR_MARKER, (string) $post->post_excerpt, 'Topic excerpt should identify Populator content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Topic should have body content.');
    }

    private static function assertRichSectionContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Section should include a heading in body content.');
        $test->assertStringContainsString(self::POPULATOR_MARKER, (string) $post->post_excerpt, 'Section excerpt should identify Populator content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Section should have body content.');
    }

    private static function assertRichModuleContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Module should include a heading in body content.');
        $test->assertStringContainsString(self::POPULATOR_MARKER, (string) $post->post_excerpt, 'Module excerpt should identify Populator content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Module should have body content.');
    }

    private static function assertRichQuizContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Quiz should include a heading in body content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Quiz should have body content.');
    }

    private static function assertRichQuestionContent(\PHPUnit\Framework\TestCase $test, \WP_Post $post): void
    {
        $test->assertStringContainsString('<h2>', (string) $post->post_content, 'Question should include a heading in body content.');
        $test->assertNotSame('', trim(strip_tags((string) $post->post_content)), 'Question should have body content.');
    }

    /** @return array{courses: string, lessons: string, quizzes: string, questions: string, user_prefix: string, topics?: string, sections?: string, modules?: string} */
    private static function postTypesFor(string $pluginSlug): array
    {
        return match ($pluginSlug) {
            'learndash' => [
                'courses'     => 'sfwd-courses',
                'lessons'     => 'sfwd-lessons',
                'topics'      => 'sfwd-topic',
                'quizzes'     => 'sfwd-quiz',
                'questions'   => 'sfwd-question',
                'user_prefix' => 'learndash_user',
            ],
            'lifterlms' => [
                'courses'     => 'course',
                'lessons'     => 'lesson',
                'quizzes'     => 'llms_quiz',
                'questions'   => 'llms_question',
                'sections'    => 'llms_section',
                'user_prefix' => 'lifterlms_user',
            ],
            'tangible-lms' => [
                'courses'     => 'tgl_course',
                'lessons'     => 'tgl_lesson',
                'quizzes'     => 'tgl_quiz',
                'questions'   => 'tgl_question',
                'modules'     => 'tgl_module',
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
