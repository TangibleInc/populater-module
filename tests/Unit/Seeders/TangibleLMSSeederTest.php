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
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('tgl_course', $args['post_type'] ?? '');
                $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));
                $this->assertStringContainsString('Tangible Populator', (string) ($args['post_excerpt'] ?? ''));

                return true;
            }))
            ->andReturn(1, 2);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
    }

    public function test_seed_lessons_creates_module_and_parents_lesson_to_it(): void
    {
        Functions\expect('wp_insert_post')
            ->twice()
            ->andReturnUsing(static function (array $args): int {
                return match ($args['post_type'] ?? '') {
                    'tgl_module' => 50,
                    'tgl_lesson' => 10,
                    default      => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);
        $this->assertSame(50, (int) $this->meta->getValue(10, '_tgl_module_id'));
        $this->assertSame(5, $this->meta->getValue(10, '_tgl_course_id'));
        $this->assertSame(5, $this->meta->getValue(50, '_tgl_course_id'));
        $moduleIds = $this->meta->getValue(5, '_populater_tgl_module_ids');
        $this->assertIsArray($moduleIds);
        $this->assertSame(50, $moduleIds[1] ?? null);
    }

    public function test_seed_lessons_reuses_cached_module_for_course(): void
    {
        $this->meta->set(5, '_populater_tgl_module_ids', [1 => 50]);

        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_lesson'
                && ($args['post_parent'] ?? 0) === 50))
            ->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);
    }

    public function test_seed_quizzes_uses_tgl_quiz_post_type_and_sets_module_meta(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_quiz'
                && ($args['post_parent'] ?? 0) === 50))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedQuizzes(1, parentId: 50, options: [
            'course_id'      => 5,
            'quiz_parent_id' => 50,
        ]);

        $this->assertSame([20], $ids);
        $this->assertSame(50, $this->meta->getValue(20, '_tgl_module_id'));
        $this->assertSame(5, $this->meta->getValue(20, '_tgl_course_id'));
    }

    public function test_seed_quizzes_attaches_to_module_not_lesson(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'tgl_quiz'
                && ($args['post_parent'] ?? 0) === 50))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 50, options: ['quiz_parent_id' => 50]);

        $this->assertSame(50, $this->meta->getValue(20, '_tgl_module_id'));
        $this->assertNull($this->meta->getValue(20, '_tgl_lesson_id'));
    }

    public function test_seed_quizzes_creates_questions_when_configured(): void
    {
        $questionNum = 0;

        Functions\expect('wp_insert_post')
            ->times(3)
            ->andReturnUsing(static function (array $args) use (&$questionNum): int {
                return match ($args['post_type'] ?? '') {
                    'tgl_quiz'      => 20,
                    'tgl_question'  => 40 + ++$questionNum,
                    default         => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 50, options: [
            'quiz_parent_id' => 50,
            'questions_per_quiz' => 2,
        ]);

        $this->assertSame(20, $this->meta->getValue(41, '_tgl_quiz_id'));
        $this->assertSame(20, $this->meta->getValue(42, '_tgl_quiz_id'));
    }

    public function test_seed_quizzes_include_dummy_quiz_content(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('tgl_quiz', $args['post_type'] ?? '');
                $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));

                return true;
            }))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, parentId: 50, options: ['quiz_parent_id' => 50]);
    }

    public function test_seed_users_returns_array_of_ids(): void
    {
        Functions\expect('wp_create_user')->times(3)->andReturn(1, 2, 3);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(3);

        $this->assertCount(3, $ids);
    }
}
