<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\LearnDash\LearnDashLmsPlugin;
use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Tests\Support\InMemoryPostMeta;
use Brain\Monkey\Functions;

class LearnDashSeederTest extends \WPTestCase
{
    private LearnDashSeeder $seeder;

    private InMemoryPostMeta $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new LearnDashSeeder(new LearnDashLmsPlugin());
        $this->meta   = (new InMemoryPostMeta())->install();
    }

    public function test_get_name_returns_learndash(): void
    {
        $this->assertSame('LearnDash LMS', $this->seeder->getName());
    }

    public function test_get_slug_returns_learndash(): void
    {
        $this->assertSame('learndash', $this->seeder->getSlug());
    }

    public function test_is_active_calls_is_plugin_active(): void
    {
        Functions\when('is_plugin_active')->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('sfwd-courses', $args['post_type'] ?? '');
                $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));
                $this->assertStringContainsString('Tangible Populator', (string) ($args['post_excerpt'] ?? ''));

                return true;
            }))
            ->andReturn(1, 2);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertSame([1, 2], $ids);
    }

    public function test_seed_courses_skips_wp_error_results(): void
    {
        $error = \Mockery::mock('WP_Error');

        Functions\expect('wp_insert_post')->times(2)->andReturn($error, 5);
        Functions\expect('is_wp_error')
            ->andReturnUsing(fn($val) => $val instanceof \Mockery\MockInterface);

        $ids = $this->seeder->seedCourses(2);

        $this->assertSame([5], $ids);
    }

    public function test_seed_courses_does_not_initialize_ld_course_steps_until_lessons_exist(): void
    {
        Functions\expect('wp_insert_post')->once()->andReturn(100);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedCourses(1);

        $this->assertNull($this->meta->getValue(100, 'ld_course_steps'));
    }

    public function test_seed_lessons_registers_lesson_in_course_steps_with_topic_slot(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => []]],
            'versions' => [],
            'empty' => [],
        ]);

        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'sfwd-lessons'))
            ->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);

        $steps = $this->meta->getValue(5, 'ld_course_steps');
        $this->assertIsArray($steps);
        $this->assertArrayHasKey(10, $steps['steps']['h']['sfwd-lessons']);
        $this->assertSame([], $steps['steps']['h']['sfwd-lessons'][10]['sfwd-topic']);
        $this->assertSame([], $steps['steps']['h']['sfwd-lessons'][10]['sfwd-quiz']);
    }

    public function test_seed_lessons_sets_course_id_meta_on_lesson(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => []]],
            'versions' => [],
            'empty' => [],
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame(5, $this->meta->getValue(10, 'course_id'));
    }

    public function test_seed_quizzes_attaches_quiz_to_topic_in_course_steps(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => [
                'h' => [
                    'sfwd-lessons' => [
                        10 => ['sfwd-topic' => [101 => []], 'sfwd-quiz' => []],
                    ],
                ],
            ],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);

        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'sfwd-quiz'))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'      => 5,
            'lesson_id'      => 10,
            'quiz_parent_id' => 110,
        ]);

        $this->assertSame([20], $ids);

        $steps = $this->meta->getValue(5, 'ld_course_steps');
        $this->assertArrayHasKey(20, $steps['steps']['h']['sfwd-lessons'][10]['sfwd-topic'][110]['sfwd-quiz']);
    }

    public function test_seed_quizzes_resolves_course_id_from_topic_lesson_meta(): void
    {
        $this->meta->set(110, 'lesson_id', 10);
        $this->meta->set(10, 'course_id', 99);
        $this->meta->set(99, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => [10 => ['sfwd-topic' => [110 => []], 'sfwd-quiz' => []]]]],
            'versions' => [],
            'empty' => [],
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: ['quiz_parent_id' => 110]);

        $steps = $this->meta->getValue(99, 'ld_course_steps');
        $this->assertArrayHasKey(20, $steps['steps']['h']['sfwd-lessons'][10]['sfwd-topic'][110]['sfwd-quiz']);
    }

    public function test_seed_quizzes_sets_quiz_pro_id_when_missing(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => [10 => ['sfwd-topic' => [110 => []], 'sfwd-quiz' => []]]]],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);

        Functions\expect('wp_insert_post')->once()->andReturn(50);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'      => 5,
            'lesson_id'      => 10,
            'quiz_parent_id' => 110,
        ]);

        $this->assertSame(50, $this->meta->getValue(50, 'quiz_pro_id'));
    }

    public function test_seed_quizzes_preserves_existing_quiz_pro_id(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => [10 => ['sfwd-topic' => [110 => []], 'sfwd-quiz' => []]]]],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);
        $this->meta->set(50, 'quiz_pro_id', 777);

        Functions\expect('wp_insert_post')->once()->andReturn(50);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'      => 5,
            'lesson_id'      => 10,
            'quiz_parent_id' => 110,
        ]);

        $this->assertSame(777, $this->meta->getValue(50, 'quiz_pro_id'));
    }

    public function test_seed_quizzes_sets_topic_and_lesson_meta_on_quiz(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => [10 => ['sfwd-topic' => [110 => []], 'sfwd-quiz' => []]]]],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);

        Functions\expect('wp_insert_post')->once()->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'      => 5,
            'lesson_id'      => 10,
            'quiz_parent_id' => 110,
        ]);

        $this->assertSame(10, $this->meta->getValue(20, 'lesson_id'));
        $this->assertSame(110, $this->meta->getValue(20, 'topic_id'));
        $this->assertSame(5, $this->meta->getValue(20, 'course_id'));
    }

    public function test_seed_quizzes_creates_questions_when_configured(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => [10 => ['sfwd-topic' => [110 => []], 'sfwd-quiz' => []]]]],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);

        $questionNum = 0;

        Functions\expect('wp_insert_post')
            ->times(3)
            ->andReturnUsing(static function (array $args) use (&$questionNum): int {
                return match ($args['post_type'] ?? '') {
                    'sfwd-quiz'      => 20,
                    'sfwd-question'  => 100 + ++$questionNum,
                    default          => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'          => 5,
            'lesson_id'          => 10,
            'quiz_parent_id'     => 110,
            'questions_per_quiz' => 2,
        ]);

        $questions = $this->meta->getValue(20, 'ld_quiz_questions');
        $this->assertIsArray($questions);
        $this->assertCount(2, $questions);
        $this->assertSame(20, $this->meta->getValue(101, 'quiz_id'));
        $this->assertSame(101, $this->meta->getValue(101, 'question_pro_id'));
    }

    public function test_seed_lessons_creates_topics_when_configured(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => []]],
            'versions' => [],
            'empty' => [],
        ]);

        $topicNum = 0;

        Functions\expect('wp_insert_post')
            ->times(3)
            ->andReturnUsing(static function (array $args) use (&$topicNum): int {
                return match ($args['post_type'] ?? '') {
                    'sfwd-lessons' => 10,
                    'sfwd-topic'   => 100 + ++$topicNum,
                    default        => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedLessons(1, courseId: 5, options: [
            'index'             => 1,
            'topics_per_lesson' => 2,
        ]);

        $steps = $this->meta->getValue(5, 'ld_course_steps');
        $this->assertCount(2, $steps['steps']['h']['sfwd-lessons'][10]['sfwd-topic']);
        $this->assertSame(5, $this->meta->getValue(101, 'course_id'));
        $this->assertSame(10, $this->meta->getValue(101, 'lesson_id'));
    }

    public function test_seed_lessons_skips_course_steps_when_course_id_is_zero(): void
    {
        Functions\expect('wp_insert_post')->once()->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 0);

        $this->assertSame([10], $ids);
        $this->assertNull($this->meta->getValue(0, 'ld_course_steps'));
    }

    public function test_seed_quizzes_creates_topic_and_lesson_entries_in_course_steps_when_missing(): void
    {
        $this->meta->set(5, 'ld_course_steps', [
            'steps' => ['h' => ['sfwd-lessons' => []]],
            'versions' => [],
            'empty' => [],
        ]);
        $this->meta->set(110, 'lesson_id', 10);

        Functions\expect('wp_insert_post')->once()->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 110, options: [
            'course_id'      => 5,
            'lesson_id'      => 10,
            'quiz_parent_id' => 110,
        ]);

        $steps = $this->meta->getValue(5, 'ld_course_steps');
        $this->assertArrayHasKey(10, $steps['steps']['h']['sfwd-lessons']);
        $this->assertArrayHasKey(20, $steps['steps']['h']['sfwd-lessons'][10]['sfwd-topic'][110]['sfwd-quiz']);
    }

    public function test_seed_users_creates_users(): void
    {
        Functions\expect('wp_create_user')->times(3)->andReturn(1, 2, 3);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('update_user_meta')->times(9)->andReturn(true);
        Functions\expect('wp_update_user')->times(3)->andReturn(1, 2, 3);

        $ids = $this->seeder->seedUsers(3);

        $this->assertCount(3, $ids);
    }

    public function test_build_seed_queue_creates_one_quiz_per_lesson_not_per_topic(): void
    {
        LmsPlugins::registerBuiltIn();
        $seeder = (new LmsPluginRegistry())->createSeeder('learndash');
        $config = SeedConfig::fromArray([
            'plugin'             => 'learndash',
            'courses'            => 1,
            'lessons_per_course' => 3,
            'topics_per_lesson'  => 2,
            'quizzes_per_lesson' => 1,
            'users'              => 0,
        ]);

        $quizItems = array_values(array_filter(
            $seeder->buildSeedQueue($config),
            static fn($item) => $item->type === 'quiz',
        ));

        $this->assertCount(3, $quizItems);
        $this->assertSame('lessons', $quizItems[0]->data['quiz_parent_entity']);
    }

    public function test_seed_users_skips_errors(): void
    {
        $error = \Mockery::mock('WP_Error');
        Functions\expect('wp_create_user')->times(2)->andReturn($error, 7);
        Functions\expect('is_wp_error')
            ->andReturnUsing(fn($val) => $val instanceof \Mockery\MockInterface);
        Functions\expect('update_user_meta')->times(3)->andReturn(true);
        Functions\expect('wp_update_user')->once()->andReturn(7);

        $ids = $this->seeder->seedUsers(2);

        $this->assertSame([7], $ids);
    }
}
