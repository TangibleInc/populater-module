<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\TangibleLMS\TangibleLmsPlugin;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeeder;
use Tangible\Populater\Tests\Support\InMemoryPostMeta;
use Brain\Monkey\Functions;

class TangibleLMSSeederTest extends \WPTestCase
{
    private TangibleLMSSeeder $seeder;

    private InMemoryPostMeta $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new TangibleLMSSeeder(new TangibleLmsPlugin());
        $this->meta   = (new InMemoryPostMeta())->install();
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
        Functions\when('is_plugin_active')->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_uses_tgl_course_post_type(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_course'))
            ->andReturn(1, 2);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
    }

    public function test_seed_lessons_parents_to_course_and_sets_tgl_course_id_meta(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_lesson'
                && ($args['post_parent'] ?? 0) === 5))
            ->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);
        $this->assertSame(5, $this->meta->getValue(10, '_tgl_course_id'));
    }

    public function test_seed_quizzes_uses_tgl_quiz_post_type_and_sets_lesson_meta(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_quiz'))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedQuizzes(1, lessonId: 15);

        $this->assertSame([20], $ids);
        $this->assertSame(15, $this->meta->getValue(20, '_tgl_lesson_id'));
    }

    public function test_seed_quizzes_attaches_to_lesson_not_course(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_quiz'
                && !isset($args['post_parent'])))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, lessonId: 15);

        $this->assertSame(15, $this->meta->getValue(20, '_tgl_lesson_id'));
        $this->assertNull($this->meta->getValue(20, '_tgl_course_id'));
    }

    public function test_seed_users_returns_array_of_ids(): void
    {
        Functions\expect('wp_create_user')->times(3)->andReturn(1, 2, 3);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(3);

        $this->assertCount(3, $ids);
    }
}
