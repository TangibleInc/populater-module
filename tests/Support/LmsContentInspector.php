<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Tangible\Populater\LMS\LifterLMS\LifterLmsTrueFalseAnswers;
use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsEntitySchema;
use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\GroupIndexResolver;

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

        if (($expected['groups'] ?? 0) > 0) {
            $test->assertSame($expected['groups'], $delta['groups'] ?? 0, 'Group count mismatch.');
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
        int $quizzesPerSection,
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
                $quizzesPerSection,
                $afterPostId,
                $questionsPerQuiz,
                $topicsPerLesson,
            ),
            'lifterlms' => self::assertLifterStructure(
                $test,
                $courses,
                $lessonsPerCourse,
                $quizzesPerSection,
                $afterPostId,
                $questionsPerQuiz,
                $sectionsPerCourse,
            ),
            'tangible-lms' => self::assertTangibleStructure(
                $test,
                $courses,
                $lessonsPerCourse,
                $quizzesPerSection,
                $afterPostId,
                $questionsPerQuiz,
                $modulesPerCourse,
            ),
            default => throw new \InvalidArgumentException(sprintf('Unknown plugin slug "%s".', $pluginSlug)),
        };
    }

    /** @return array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} */
    public static function snapshotAfter(string $pluginSlug, int $afterPostId): array
    {
        $types = self::schemaFor($pluginSlug)->postTypesForSnapshot();

        return [
            'courses'   => self::countPostsAfter($types['courses'], $afterPostId),
            'lessons'   => self::countPostsAfter($types['lessons'], $afterPostId),
            'topics'    => isset($types['topics']) ? self::countPostsAfter($types['topics'], $afterPostId) : 0,
            'quizzes'   => self::countPostsAfter($types['quizzes'], $afterPostId),
            'questions' => self::countPostsAfter($types['questions'], $afterPostId),
            'sections'  => isset($types['sections']) ? self::countPostsAfter($types['sections'], $afterPostId) : 0,
            'modules'   => isset($types['modules']) ? self::countPostsAfter($types['modules'], $afterPostId) : 0,
            'groups'    => self::countGroupsAfter($pluginSlug, $afterPostId),
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
            'groups'    => ($after['groups'] ?? 0) - ($before['groups'] ?? 0),
            'users'     => $after['users'] - $before['users'],
        ];
    }

    public static function expectedCounts(
        string $pluginSlug,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerSection,
        int $users,
        int $questionsPerQuiz = 3,
        int $topicsPerLesson = 2,
        int $sectionsPerCourse = 1,
        int $modulesPerCourse = 1,
        int $groups = 0,
    ): array {
        $lessons  = $courses * $lessonsPerCourse;
        $topics   = $lessons * $topicsPerLesson;
        $sections = $courses * $sectionsPerCourse;
        $modules  = $courses * $modulesPerCourse;
        $quizzes  = match ($pluginSlug) {
            'learndash'    => $topics * $quizzesPerSection,
            'lifterlms'    => $sections * $quizzesPerSection,
            'tangible-lms' => $modules * $quizzesPerSection,
            default        => $lessons * $quizzesPerSection,
        };
        $questions = $quizzes * $questionsPerQuiz;

        return [
            'courses'   => $courses,
            'lessons'   => $lessons,
            'topics'    => $topics,
            'quizzes'   => $quizzes,
            'questions' => $questions,
            'sections'  => $sections,
            'modules'   => $modules,
            'users'     => $users + ($groups > 0 ? $groups : 0),
            'groups'    => $groups,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function expectedQueueTotal(string $pluginSlug, array $config): int
    {
        LmsPlugins::registerBuiltIn();
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder($pluginSlug);
        $seedConfig = SeedConfig::fromArray(array_merge($config, ['plugin' => $pluginSlug]));

        return count($seeder->buildSeedQueue($seedConfig));
    }

    public static function groupsSupportedFor(string $pluginSlug): bool
    {
        return match ($pluginSlug) {
            'lifterlms'    => function_exists('llms_create_group'),
            'learndash'    => function_exists('post_type_exists') && post_type_exists('groups'),
            'tangible-lms' => false,
            default        => false,
        };
    }

    /**
     * Asserts groups exist and contain the expected admins, students, and roles.
     */
    public static function assertSeededGroups(
        \PHPUnit\Framework\TestCase $test,
        string $pluginSlug,
        int $groups,
        int $users,
        int $afterPostId = 0,
    ): void {
        if ($groups <= 0) {
            return;
        }

        match ($pluginSlug) {
            'lifterlms' => self::assertLifterLmsGroups($test, $groups, $users, $afterPostId),
            'learndash' => self::assertLearnDashGroups($test, $groups, $users, $afterPostId),
            default     => null,
        };
    }

    private static function assertLifterLmsGroups(
        \PHPUnit\Framework\TestCase $test,
        int $groups,
        int $users,
        int $afterPostId,
    ): void {
        if (!class_exists(\LLMS_Groups_Enrollment::class)) {
            $test->markTestSkipped('LifterLMS Groups add-on is not active.');
        }

        $groupPosts = self::newestPosts('llms_group', $groups, $afterPostId);
        usort(
            $groupPosts,
            static fn(\WP_Post $a, \WP_Post $b): int => (int) $a->ID <=> (int) $b->ID,
        );
        $test->assertCount($groups, $groupPosts, 'Expected seeded LifterLMS groups to exist.');

        $groupIdsByIndex = [];

        foreach ($groupPosts as $offset => $groupPost) {
            $index = $offset + 1;
            $groupIdsByIndex[$index] = (int) $groupPost->ID;
            $group = get_llms_group($groupPost);

            $test->assertInstanceOf(\LLMS_Group::class, $group);
            $test->assertGreaterThanOrEqual(2, (int) $group->get('seats'), 'Group should have enough seats.');
        }

        for ($g = 1; $g <= $groups; $g++) {
            $admin = get_user_by('email', 'groupadmin' . $g . '@example.com');
            $test->assertInstanceOf(\WP_User::class, $admin, "Missing groupadmin{$g}.");

            $groupId = $groupIdsByIndex[$g];
            $test->assertTrue(
                llms_group_is_user_primary_admin((int) $admin->ID, $groupId),
                "groupadmin{$g} should be primary admin of group {$g}.",
            );
            $test->assertSame(
                'admin',
                \LLMS_Groups_Enrollment::get_role((int) $admin->ID, $groupId),
                "groupadmin{$g} should have admin group role.",
            );
        }

        $studentsByGroup = array_fill(1, $groups, []);

        for ($u = 1; $u <= $users; $u++) {
            $student = get_user_by('email', 'student' . $u . '@example.com');
            $test->assertInstanceOf(\WP_User::class, $student, "Missing student{$u}.");

            $groupIndex = GroupIndexResolver::resolve($u, $users, $groups);
            $groupId    = $groupIdsByIndex[$groupIndex];

            $test->assertTrue(
                llms_is_user_enrolled((int) $student->ID, $groupId),
                "student{$u} should be enrolled in group {$groupIndex}.",
            );
            $test->assertSame(
                'member',
                \LLMS_Groups_Enrollment::get_role((int) $student->ID, $groupId),
                "student{$u} should be a group member.",
            );

            self::assertSeededStudentProfile($test, (int) $student->ID, $u);

            $studentsByGroup[$groupIndex][] = (int) $student->ID;
        }

        for ($g = 1; $g <= $groups; $g++) {
            $expectedStudents = count(array_filter(
                range(1, $users),
                static fn(int $userIndex): bool => GroupIndexResolver::resolve($userIndex, $users, $groups) === $g,
            ));

            $test->assertCount(
                $expectedStudents,
                $studentsByGroup[$g],
                "Group {$g} should contain all assigned students.",
            );

            if (!function_exists('llms_group_get_members')) {
                continue;
            }

            $query = llms_group_get_members($groupIdsByIndex[$g], ['per_page' => 500]);
            $test->assertSame(
                $expectedStudents + 1,
                (int) $query->get_query()->get_found_results(),
                "Group {$g} should contain its admin and all assigned students.",
            );
        }
    }

    private static function assertLearnDashGroups(
        \PHPUnit\Framework\TestCase $test,
        int $groups,
        int $users,
        int $afterPostId,
    ): void {
        if (!function_exists('learndash_get_groups_administrator_ids')) {
            $test->markTestSkipped('LearnDash groups API is not available.');
        }

        $groupPosts = self::newestPosts('groups', $groups, $afterPostId);
        usort(
            $groupPosts,
            static fn(\WP_Post $a, \WP_Post $b): int => (int) $a->ID <=> (int) $b->ID,
        );
        $test->assertCount($groups, $groupPosts, 'Expected seeded LearnDash groups to exist.');

        $groupIdsByIndex = [];

        foreach ($groupPosts as $offset => $groupPost) {
            $groupIdsByIndex[$offset + 1] = (int) $groupPost->ID;
        }

        for ($g = 1; $g <= $groups; $g++) {
            $admin = get_user_by('email', 'groupadmin' . $g . '@example.com');
            $test->assertInstanceOf(\WP_User::class, $admin, "Missing groupadmin{$g}.");

            $groupId = $groupIdsByIndex[$g];
            $leaderIds = array_map('intval', learndash_get_groups_administrator_ids($groupId));

            $test->assertContains(
                (int) $admin->ID,
                $leaderIds,
                "groupadmin{$g} should be a LearnDash group leader.",
            );
        }

        $studentsByGroup = array_fill(1, $groups, []);

        for ($u = 1; $u <= $users; $u++) {
            $student = get_user_by('email', 'student' . $u . '@example.com');
            $test->assertInstanceOf(\WP_User::class, $student, "Missing student{$u}.");

            $groupIndex = GroupIndexResolver::resolve($u, $users, $groups);
            $groupId    = $groupIdsByIndex[$groupIndex];
            $memberIds  = array_map('intval', learndash_get_groups_user_ids($groupId));

            $test->assertContains(
                (int) $student->ID,
                $memberIds,
                "student{$u} should belong to group {$groupIndex}.",
            );

            $studentsByGroup[$groupIndex][] = (int) $student->ID;
        }

        for ($g = 1; $g <= $groups; $g++) {
            $expectedStudents = count(array_filter(
                range(1, $users),
                static fn(int $userIndex): bool => GroupIndexResolver::resolve($userIndex, $users, $groups) === $g,
            ));

            $test->assertCount(
                $expectedStudents,
                $studentsByGroup[$g],
                "LearnDash group {$g} should contain all assigned students.",
            );
        }
    }

    private static function countGroupsAfter(string $pluginSlug, int $afterPostId): int
    {
        $schema = self::schemaFor($pluginSlug);

        if (!isset($schema->postTypes['groups'])) {
            return 0;
        }

        return self::countPostsAfter($schema->getPostType('groups'), $afterPostId);
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
        int $quizzesPerSection,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $topicsPerLesson = 2,
    ): void {
        $schema      = self::schemaFor('learndash');
        $courseType  = $schema->getPostType('courses');
        $lessonType  = $schema->getPostType('lessons');
        $topicType   = $schema->getPostType('topics');
        $quizType    = $schema->getPostType('quizzes');
        $questionType = $schema->getPostType('questions');
        $coursePosts = self::newestPosts($courseType, $courses, $afterPostId);

        $test->assertCount($courses, $coursePosts, 'Expected newly created LearnDash courses.');

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);

            $steps = get_post_meta($course->ID, 'ld_course_steps', true);
            $test->assertIsArray($steps, 'Course should have ld_course_steps meta.');
            $lessonSteps = $steps['steps']['h'][$lessonType] ?? [];
            $test->assertCount($lessonsPerCourse, $lessonSteps, 'Course should contain expected lessons in ld_course_steps.');

            foreach ($lessonSteps as $lessonId => $entry) {
                if ($topicsPerLesson > 0) {
                    $test->assertArrayHasKey($topicType, $entry, 'LearnDash lesson step should reserve topic post type.');
                    $test->assertIsArray($entry[$topicType]);
                    $test->assertCount($topicsPerLesson, $entry[$topicType], 'Lesson should contain expected topics in ld_course_steps.');

                    if ($quizzesPerSection > 0) {
                        foreach ($entry[$topicType] as $topicEntry) {
                            $test->assertArrayHasKey($quizType, $topicEntry, 'LearnDash topic step should reserve quiz post type.');
                            $test->assertCount($quizzesPerSection, $topicEntry[$quizType], 'Topic should contain expected quizzes in ld_course_steps.');
                        }
                    }
                }

                $test->assertSame($courses > 0 ? $course->ID : 0, (int) get_post_meta((int) $lessonId, 'course_id', true));
            }
        }

        $lessonPosts = self::newestPosts($lessonType, $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessonPosts as $lesson) {
            self::assertRichLessonContent($test, $lesson);
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, 'course_id', true), 'LearnDash lesson should reference a course.');
        }

        if ($topicsPerLesson > 0) {
            $topics = self::newestPosts(
                $topicType,
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
            $quizType,
            $courses * $lessonsPerCourse * $topicsPerLesson * $quizzesPerSection,
            $afterPostId,
        );

        foreach ($quizPosts as $quiz) {
            self::assertRichQuizContent($test, $quiz);
            $proId = (int) get_post_meta($quiz->ID, 'quiz_pro_id', true);
            $test->assertGreaterThan(0, $proId, 'LearnDash quiz should have quiz_pro_id meta.');
            self::assertLearnDashProQuizExists($test, $proId);
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, 'lesson_id', true), 'LearnDash quiz should reference a lesson.');
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, 'topic_id', true), 'LearnDash quiz should reference a topic.');
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, 'course_id', true), 'LearnDash quiz should reference a course.');

            if ($questionsPerQuiz > 0) {
                $questionIds = get_post_meta($quiz->ID, 'ld_quiz_questions', true);
                $test->assertIsArray($questionIds, 'LearnDash quiz should have ld_quiz_questions meta.');
                $test->assertCount($questionsPerQuiz, $questionIds, 'LearnDash quiz should contain expected questions.');

                foreach ($questionIds as $questionPostId => $questionProId) {
                    $test->assertSame(
                        $quiz->ID,
                        (int) get_post_meta((int) $questionPostId, 'quiz_id', true),
                        'LearnDash question should reference its parent quiz.',
                    );
                    $test->assertNotSame(
                        (int) $questionPostId,
                        (int) $questionProId,
                        'LearnDash ld_quiz_questions should map question post IDs to ProQuiz IDs.',
                    );
                }
            }

            self::assertLearnDashQuizWorks(
                $test,
                (int) $quiz->ID,
                (int) get_post_meta($quiz->ID, 'course_id', true),
                (int) get_post_meta($quiz->ID, 'lesson_id', true),
            );
        }

        if ($questionsPerQuiz > 0) {
            $questionPosts = self::newestPosts(
                $questionType,
                $courses * $lessonsPerCourse * $topicsPerLesson * $quizzesPerSection * $questionsPerQuiz,
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
        int $quizzesPerSection,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $sectionsPerCourse = 1,
    ): void {
        $schema       = self::schemaFor('lifterlms');
        $courseType   = $schema->getPostType('courses');
        $sectionType  = $schema->getPostType('sections');
        $lessonType   = $schema->getPostType('lessons');
        $quizType     = $schema->getPostType('quizzes');
        $questionType = $schema->getPostType('questions');
        $container    = $schema->container;
        $coursePosts  = self::newestPosts($courseType, $courses, $afterPostId);

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);
            self::assertLifterCourseEnrollmentReady($test, (int) $course->ID);
        }

        $checkoutPageId = (int) get_option('lifterlms_checkout_page_id', 0);
        $test->assertGreaterThan(0, $checkoutPageId, 'LifterLMS checkout page should be assigned.');
        $test->assertStringContainsString(
            '[lifterlms_checkout]',
            (string) get_post_field('post_content', $checkoutPageId),
            'LifterLMS checkout page should contain the checkout shortcode.',
        );

        foreach ($coursePosts as $course) {
            $test->assertStringContainsString(
                'llms/pricing-table',
                (string) $course->post_content,
                'LifterLMS course should include the pricing table block for enrollment UI.',
            );
            $test->assertStringContainsString(
                'llms/course-syllabus',
                (string) $course->post_content,
                'LifterLMS course should include the syllabus block for the course outline.',
            );
        }

        $sections = self::newestPosts($sectionType, $courses * $sectionsPerCourse, $afterPostId);
        $test->assertCount($courses * $sectionsPerCourse, $sections, 'LifterLMS should create expected sections per course.');

        foreach ($sections as $section) {
            $test->assertSame('section', $section->post_type, 'LifterLMS sections must use the section post type.');
            $test->assertGreaterThan(0, (int) $section->post_parent, 'Section should be attached to a course.');
            self::assertRichSectionContent($test, $section);
            $test->assertSame(
                (int) $section->post_parent,
                (int) get_post_meta($section->ID, $container?->parentMetaKey ?? '_llms_parent_course', true),
            );
            $test->assertNotSame('', (string) get_post_meta($section->ID, '_llms_order', true), 'LifterLMS section should have order meta.');
        }

        if ($courses > 0 && $lessonsPerCourse > 0 && class_exists(\LLMS_Course::class)) {
            foreach ($coursePosts as $course) {
                $llmsCourse = new \LLMS_Course((int) $course->ID);
                $test->assertGreaterThan(
                    0,
                    count($llmsCourse->get_sections()),
                    'LifterLMS course should expose sections in the syllabus.',
                );
            }
        }

        $lessons = self::newestPosts($lessonType, $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            self::assertRichLessonContent($test, $lesson);
            $sectionId = (int) get_post_meta($lesson->ID, $container?->lessonParentMetaKey ?? '_llms_parent_section', true);
            $test->assertGreaterThan(0, $sectionId, 'LifterLMS lesson should belong to a section.');
            $test->assertSame($sectionId, (int) $lesson->post_parent, 'LifterLMS lesson post_parent should be the section.');
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, '_llms_parent_course', true));
            $test->assertNotSame('', (string) get_post_meta($lesson->ID, '_llms_order', true), 'LifterLMS lesson should have order meta.');
        }

        if ($quizzesPerSection > 0) {
            $lessonsWithQuiz = array_values(array_filter(
                $lessons,
                static fn(\WP_Post $lesson) => (int) get_post_meta($lesson->ID, '_llms_quiz', true) > 0,
            ));
            $test->assertGreaterThanOrEqual(
                min($quizzesPerSection * count($sections), count($lessons)),
                count($lessonsWithQuiz),
                'LifterLMS should assign quizzes to lessons within sections.',
            );
        }

        $quizzes = self::newestPosts(
            $quizType,
            $courses * $sectionsPerCourse * $quizzesPerSection,
            $afterPostId,
        );

        foreach ($quizzes as $quiz) {
            self::assertRichQuizContent($test, $quiz);
            $lessonId = (int) get_post_meta($quiz->ID, '_llms_lesson_id', true);
            $test->assertGreaterThan(0, $lessonId, 'LifterLMS quiz should reference a lesson.');
            $test->assertSame('yes', get_post_meta($lessonId, '_llms_quiz_enabled', true));
            $test->assertSame($quiz->ID, (int) get_post_meta($lessonId, '_llms_quiz', true));
        }

        if ($questionsPerQuiz > 0) {
            $questions = self::newestPosts(
                $questionType,
                $courses * $sectionsPerCourse * $quizzesPerSection * $questionsPerQuiz,
                $afterPostId,
            );

            foreach ($questions as $question) {
                self::assertRichQuestionContent($test, $question);
                $quizId = (int) get_post_meta($question->ID, '_llms_parent_id', true);
                $test->assertGreaterThan(0, $quizId, 'LifterLMS question should reference a quiz.');
                $test->assertSame('true_false', get_post_meta($question->ID, '_llms_question_type', true));
                $test->assertSame($quizType, get_post_type($quizId), 'LifterLMS question should belong to a quiz post.');

                $marker = (string) get_post_meta(
                    $question->ID,
                    LifterLmsTrueFalseAnswers::CORRECT_MARKER_META,
                    true,
                );
                $test->assertContains(
                    $marker,
                    [LifterLmsTrueFalseAnswers::TRUE_MARKER, LifterLmsTrueFalseAnswers::FALSE_MARKER],
                    'LifterLMS question should record which choice marker is correct for stress tests.',
                );

                if (function_exists('llms_get_post')) {
                    $llmsQuestion = llms_get_post((int) $question->ID);

                    if ($llmsQuestion instanceof \LLMS_Question) {
                        $choices = $llmsQuestion->get_choices();
                        $test->assertGreaterThanOrEqual(
                            2,
                            count($choices),
                            'LifterLMS true/false questions need True/False answer choices.',
                        );

                        $correctCount = 0;

                        foreach ($choices as $choice) {
                            if ($choice->is_correct()) {
                                $correctCount++;
                                $test->assertSame($marker, $choice->get('marker'));
                            }
                        }

                        $test->assertSame(
                            1,
                            $correctCount,
                            'LifterLMS question should have exactly one correct choice.',
                        );
                    }
                }

                $test->assertStringContainsString(
                    'populater-stress-hint',
                    (string) $question->post_content,
                    'LifterLMS question should include a stress-test hint for automation.',
                );
            }
        }
    }

    public static function assertTangibleStructure(
        \PHPUnit\Framework\TestCase $test,
        int $courses,
        int $lessonsPerCourse,
        int $quizzesPerSection,
        int $afterPostId = 0,
        int $questionsPerQuiz = 3,
        int $modulesPerCourse = 1,
    ): void {
        $schema       = self::schemaFor('tangible-lms');
        $courseType   = $schema->getPostType('courses');
        $moduleType   = $schema->getPostType('modules');
        $lessonType   = $schema->getPostType('lessons');
        $quizType     = $schema->getPostType('quizzes');
        $questionType = $schema->getPostType('questions');
        $container    = $schema->container;
        $coursePosts  = self::newestPosts($courseType, $courses, $afterPostId);

        foreach ($coursePosts as $course) {
            self::assertRichCourseContent($test, $course);
        }

        $modules = self::newestPosts($moduleType, $courses * $modulesPerCourse, $afterPostId);
        $test->assertCount($courses * $modulesPerCourse, $modules, 'Tangible LMS should create expected modules per course.');

        foreach ($modules as $module) {
            $test->assertGreaterThan(0, (int) $module->post_parent, 'Module should be attached to a course.');
            self::assertRichModuleContent($test, $module);
            $test->assertSame(
                (int) $module->post_parent,
                (int) get_post_meta($module->ID, $container?->parentMetaKey ?? '_tgl_course_id', true),
            );
        }

        $lessons = self::newestPosts($lessonType, $courses * $lessonsPerCourse, $afterPostId);

        foreach ($lessons as $lesson) {
            self::assertRichLessonContent($test, $lesson);
            $moduleId = (int) get_post_meta($lesson->ID, '_tgl_module_id', true);
            $test->assertGreaterThan(0, $moduleId, 'Tangible lesson should belong to a module.');
            $test->assertSame($moduleId, (int) $lesson->post_parent, 'Tangible lesson post_parent should be the module.');
            $test->assertGreaterThan(0, (int) get_post_meta($lesson->ID, '_tgl_course_id', true));
        }

        $quizzes = self::newestPosts(
            $quizType,
            $courses * $modulesPerCourse * $quizzesPerSection,
            $afterPostId,
        );

        foreach ($quizzes as $quiz) {
            self::assertRichQuizContent($test, $quiz);
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_tgl_module_id', true), 'Tangible quiz should reference a module.');
            $test->assertGreaterThan(0, (int) get_post_meta($quiz->ID, '_tgl_course_id', true), 'Tangible quiz should reference a course.');
        }

        if ($questionsPerQuiz > 0) {
            $questions = self::newestPosts(
                $questionType,
                $courses * $modulesPerCourse * $quizzesPerSection * $questionsPerQuiz,
                $afterPostId,
            );

            foreach ($questions as $question) {
                self::assertRichQuestionContent($test, $question);
                $quizId = (int) get_post_meta($question->ID, '_tgl_quiz_id', true);
                $test->assertGreaterThan(0, $quizId, 'Tangible question should reference a quiz.');
                $test->assertSame($quizType, get_post_type($quizId), 'Tangible question should belong to a quiz post.');
            }
        }
    }

    private static function assertLifterCourseEnrollmentReady(\PHPUnit\Framework\TestCase $test, int $courseId): void
    {
        $plans = get_posts([
            'post_type'      => 'llms_access_plan',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_llms_product_id',
                    'value' => $courseId,
                ],
                [
                    'key'   => '_llms_is_free',
                    'value' => 'yes',
                ],
            ],
        ]);

        $test->assertNotEmpty($plans, 'LifterLMS course should have a free access plan.');
        $test->assertSame('yes', get_post_meta($plans[0]->ID, '_llms_is_free', true));

        if (function_exists('llms_get_post')) {
            $product = llms_get_post($courseId);

            if (is_object($product) && method_exists($product, 'has_free_access_plan')) {
                $test->assertTrue($product->has_free_access_plan(), 'LifterLMS course should report a free access plan.');
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

    private static function schemaFor(string $pluginSlug): LmsEntitySchema
    {
        $registered = AbstractLmsPlugin::getRegistered();

        if (!isset($registered[$pluginSlug])) {
            LmsPlugins::registerBuiltIn();
            $registered = AbstractLmsPlugin::getRegistered();
        }

        if (!isset($registered[$pluginSlug])) {
            throw new \InvalidArgumentException(sprintf('Unknown plugin slug "%s".', $pluginSlug));
        }

        return $registered[$pluginSlug]->getEntitySchema();
    }

    private static function assertLearnDashProQuizExists(\PHPUnit\Framework\TestCase $test, int $proId): void
    {
        if (!class_exists(\WpProQuiz_Model_QuizMapper::class)) {
            return;
        }

        $mapper = new \WpProQuiz_Model_QuizMapper();
        $test->assertGreaterThan(
            0,
            (int) $mapper->exists($proId),
            'LearnDash quiz_pro_id should reference an existing ProQuiz record.',
        );
    }

    /**
     * Verifies a quiz is linked to its course/lesson and has playable ProQuiz content.
     */
    private static function assertLearnDashQuizWorks(
        \PHPUnit\Framework\TestCase $test,
        int $quizPostId,
        int $courseId,
        int $lessonId,
    ): void {
        $test->assertGreaterThan(0, $courseId, 'LearnDash quiz should belong to a course.');
        $test->assertGreaterThan(0, $lessonId, 'LearnDash quiz should belong to a lesson.');

        if (!function_exists('learndash_get_courses_for_step')) {
            return;
        }

        $coursesForStep = learndash_get_courses_for_step($quizPostId, true);
        $test->assertArrayHasKey(
            $courseId,
            $coursesForStep,
            'LearnDash should associate the quiz with its course for nested URLs.',
        );

        if (!function_exists('learndash_course_get_all_parent_step_ids')) {
            return;
        }

        $parentStepIds = learndash_course_get_all_parent_step_ids($courseId, $quizPostId);
        $test->assertContains(
            $lessonId,
            $parentStepIds,
            'LearnDash course steps should list the quiz parent lesson.',
        );

        if (!function_exists('learndash_get_setting')) {
            return;
        }

        $quizProId = (int) learndash_get_setting($quizPostId, 'quiz_pro');
        $test->assertSame(
            $quizProId,
            (int) get_post_meta($quizPostId, 'quiz_pro_id', true),
            'LearnDash quiz_pro setting should match quiz_pro_id meta.',
        );

        if (!class_exists(\WpProQuiz_Model_QuestionMapper::class) || $quizProId <= 0) {
            return;
        }

        $questionMapper = new \WpProQuiz_Model_QuestionMapper();
        $proQuestions   = $questionMapper->fetchAll($quizProId);
        $test->assertNotEmpty($proQuestions, 'LearnDash quiz should have ProQuiz questions attached.');

        foreach ($proQuestions as $proQuestion) {
            $answers = $proQuestion->getAnswerData();
            $test->assertNotEmpty($answers, 'LearnDash ProQuiz question should include answer choices.');
        }
    }

    private static function countPosts(string $postType): int
    {
        return self::countPostsAfter($postType, 0);
    }

    private static function countPostsAfter(string $postType, int $afterPostId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d",
                $postType,
                $afterPostId,
            ),
        );
    }

    /** @return array{courses: int, lessons: int, topics: int, quizzes: int, questions: int, sections: int, modules: int, users: int} */
    public static function snapshot(string $pluginSlug): array
    {
        return self::snapshotAfter($pluginSlug, 0);
    }

    public static function assertSeededStudentProfile(
        \PHPUnit\Framework\TestCase $test,
        int $userId,
        int $index,
    ): void {
        $test->assertSame('Student', get_user_meta($userId, 'first_name', true));
        $test->assertSame((string) $index, get_user_meta($userId, 'last_name', true));
        $test->assertSame("{$index} Populater Lane", get_user_meta($userId, 'llms_billing_address_1', true));
        $test->assertSame('Testville', get_user_meta($userId, 'llms_billing_city', true));
        $test->assertSame('US', get_user_meta($userId, 'llms_billing_country', true));
    }

    private static function countUsers(string $prefix): int
    {
        global $wpdb;

        unset($prefix);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
                AbstractSeeder::USER_META_MARKER,
                '1',
            ),
        );
    }
}
