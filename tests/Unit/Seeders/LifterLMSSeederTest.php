<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\LifterLMS\LifterLmsPlugin;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeeder;
use Brain\Monkey\Functions;

class LifterLMSSeederTest extends \WPTestCase
{
    private LifterLMSSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new LifterLMSSeeder(new LifterLmsPlugin());
    }

    public function test_get_name_returns_lifterlms(): void
    {
        $this->assertSame('LifterLMS', $this->seeder->getName());
    }

    public function test_get_slug_returns_lifterlms(): void
    {
        $this->assertSame('lifterlms', $this->seeder->getSlug());
    }

    public function test_is_active_checks_lifterlms_plugin_file(): void
    {
        Functions\when('is_plugin_active')
            ->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'course'))
            ->andReturn(1, 2);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
    }

    public function test_seed_lessons_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'lesson'))
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
            ->with(\Mockery::on(fn($args) => isset($args['post_type']) && $args['post_type'] === 'llms_quiz'))
            ->andReturn(20);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);

        $ids = $this->seeder->seedQuizzes(1, lessonId: 1);

        $this->assertSame([20], $ids);
    }

    public function test_seed_users_creates_students(): void
    {
        Functions\expect('wp_create_user')
            ->times(2)
            ->andReturn(1, 2);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(2);

        $this->assertCount(2, $ids);
    }
}
