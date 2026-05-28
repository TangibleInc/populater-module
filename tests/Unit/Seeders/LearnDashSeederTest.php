<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Brain\Monkey\Functions;

class LearnDashSeederTest extends \WPTestCase
{
    private LearnDashSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new LearnDashSeeder();
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
        Functions\when('is_plugin_active')
            ->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'sfwd-courses'))
            ->andReturn(1, 2);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
        $this->assertSame([1, 2], $ids);
    }

    public function test_seed_courses_skips_wp_error_results(): void
    {
        $error = \Mockery::mock('WP_Error');

        Functions\expect('wp_insert_post')
            ->times(2)
            ->andReturn($error, 5);

        Functions\expect('is_wp_error')
            ->andReturnUsing(fn($val) => $val instanceof \Mockery\MockInterface);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(1, $ids);
        $this->assertSame([5], $ids);
    }

    public function test_seed_lessons_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'sfwd-lessons'))
            ->andReturn(10);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);

        $ids = $this->seeder->seedLessons(1, courseId: 1);

        $this->assertSame([10], $ids);
    }

    public function test_seed_quizzes_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'sfwd-quiz'))
            ->andReturn(20);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);

        $ids = $this->seeder->seedQuizzes(1, lessonId: 1);

        $this->assertSame([20], $ids);
    }

    public function test_seed_users_creates_users(): void
    {
        Functions\expect('wp_create_user')
            ->times(3)
            ->andReturn(1, 2, 3);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(3);

        $this->assertCount(3, $ids);
    }

    public function test_seed_users_skips_errors(): void
    {
        $error = \Mockery::mock('WP_Error');
        Functions\expect('wp_create_user')
            ->times(2)
            ->andReturn($error, 7);

        Functions\expect('is_wp_error')
            ->andReturnUsing(fn($val) => $val instanceof \Mockery\MockInterface);

        $ids = $this->seeder->seedUsers(2);

        $this->assertSame([7], $ids);
    }

    public function test_seed_courses_initializes_ld_course_steps(): void
    {
        /** @var list<array{0: int, 1: string, 2: mixed}> $metaCalls */
        $metaCalls = [];

        Functions\expect('wp_insert_post')->once()->andReturn(100);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->alias(
            static function (int $postId, string $key, mixed $value) use (&$metaCalls): bool {
                $metaCalls[] = [$postId, $key, $value];
                return true;
            }
        );

        $ids = $this->seeder->seedCourses(1);

        $this->assertSame([100], $ids);
        $this->assertTrue(
            (bool) array_filter($metaCalls, static fn(array $call) => $call[1] === 'ld_course_steps'),
        );
    }

    public function test_seed_quizzes_sets_quiz_pro_id(): void
    {
        /** @var list<string> $metaKeys */
        $metaKeys = [];

        Functions\expect('wp_insert_post')->once()->andReturn(50);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('update_post_meta')->alias(
            static function (int $postId, string $key, mixed $value) use (&$metaKeys): bool {
                $metaKeys[] = $key;
                return true;
            }
        );

        $ids = $this->seeder->seedQuizzes(1, lessonId: 10, options: ['course_id' => 5]);

        $this->assertSame([50], $ids);
        $this->assertContains('quiz_pro_id', $metaKeys);
    }
}
