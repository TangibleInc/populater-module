<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\TangibleLMS\TangibleLmsPlugin;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeeder;
use Brain\Monkey\Functions;

class TangibleLMSSeederTest extends \WPTestCase
{
    private TangibleLMSSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new TangibleLMSSeeder(new TangibleLmsPlugin());
    }

    public function test_get_name_returns_tangible_lms(): void
    {
        $this->assertSame('Tangible LMS', $this->seeder->getName());
    }

    public function test_get_slug_returns_tangible_lms(): void
    {
        $this->assertSame('tangible-lms', $this->seeder->getSlug());
    }

    public function test_is_active_checks_tangible_lms_plugin_file(): void
    {
        Functions\when('is_plugin_active')
            ->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_returns_array_of_ids(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->andReturn(1, 2);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
    }

    public function test_seed_lessons_returns_array_of_ids(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->andReturn(10);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);

        $ids = $this->seeder->seedLessons(1, courseId: 1);

        $this->assertSame([10], $ids);
    }

    public function test_seed_quizzes_returns_array_of_ids(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->andReturn(20);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);

        $ids = $this->seeder->seedQuizzes(1, lessonId: 1);

        $this->assertSame([20], $ids);
    }

    public function test_seed_users_returns_array_of_ids(): void
    {
        Functions\expect('wp_create_user')
            ->times(3)
            ->andReturn(1, 2, 3);

        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(3);

        $this->assertCount(3, $ids);
    }
}
