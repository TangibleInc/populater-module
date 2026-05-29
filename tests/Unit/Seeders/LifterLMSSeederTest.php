<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\LMS\LifterLMS\LifterLmsPlugin;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeeder;
use Tangible\Populater\Tests\Support\InMemoryPostMeta;
use Brain\Monkey\Functions;

class LifterLMSSeederTest extends \WPTestCase
{
    private LifterLMSSeeder $seeder;

    private InMemoryPostMeta $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new LifterLMSSeeder(new LifterLmsPlugin());
        $this->meta   = (new InMemoryPostMeta())->install();
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
        Functions\when('is_plugin_active')->justReturn(true);

        $this->assertTrue($this->seeder->isActive());
    }

    public function test_seed_courses_creates_posts_with_correct_type(): void
    {
        Functions\expect('wp_insert_post')
            ->times(2)
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('course', $args['post_type'] ?? '');
                $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));
                $this->assertStringContainsString('Tangible Populator', (string) ($args['post_excerpt'] ?? ''));

                return true;
            }))
            ->andReturn(1, 2);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(2);

        $this->assertCount(2, $ids);
    }

    public function test_seed_lessons_creates_section_and_parents_lesson_to_it(): void
    {
        Functions\expect('wp_insert_post')
            ->twice()
            ->andReturnUsing(static function (array $args): int {
                return match ($args['post_type'] ?? '') {
                    'llms_section' => 50,
                    'lesson'       => 10,
                    default        => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);
        $this->assertSame(50, $this->meta->getValue(10, '_llms_parent_section'));
        $this->assertSame(5, $this->meta->getValue(10, '_llms_parent_course'));
        $this->assertSame(5, $this->meta->getValue(50, '_llms_parent_course'));
        $sectionIds = $this->meta->getValue(5, '_populater_llms_section_ids');
        $this->assertIsArray($sectionIds);
        $this->assertSame(50, $sectionIds[1] ?? null);
    }

    public function test_seed_lessons_creates_section_with_dummy_content(): void
    {
        Functions\expect('wp_insert_post')
            ->twice()
            ->with(\Mockery::on(function (array $args): bool {
                if (($args['post_type'] ?? '') === 'llms_section') {
                    $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));
                    $this->assertStringContainsString('Tangible Populator', (string) ($args['post_excerpt'] ?? ''));
                }

                return true;
            }))
            ->andReturnUsing(static fn(array $args) => ($args['post_type'] ?? '') === 'llms_section' ? 50 : 10);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedLessons(1, courseId: 5);
    }

    public function test_seed_lessons_reuses_cached_section_for_course(): void
    {
        $this->meta->set(5, '_populater_llms_section_ids', [1 => 50]);

        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'lesson'
                && ($args['post_parent'] ?? 0) === 50))
            ->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5);

        $this->assertSame([10], $ids);
    }

    public function test_seed_lessons_reuses_section_id_from_options(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'lesson'
                && ($args['post_parent'] ?? 0) === 88))
            ->andReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedLessons(1, courseId: 5, options: ['section_id' => 88]);

        $this->assertSame([10], $ids);
    }

    public function test_seed_quizzes_sets_llms_lesson_id_meta(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'llms_quiz'))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, lessonId: 15);

        $this->assertSame(15, $this->meta->getValue(20, '_llms_lesson_id'));
    }

    public function test_seed_quizzes_attaches_to_lesson_not_course(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'llms_quiz'
                && !isset($args['post_parent'])))
            ->andReturn(20);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, lessonId: 15);

        $this->assertSame(15, $this->meta->getValue(20, '_llms_lesson_id'));
        $this->assertNull($this->meta->getValue(20, '_llms_parent_course'));
    }

    public function test_seed_quizzes_creates_questions_when_configured(): void
    {
        Functions\expect('wp_insert_post')
            ->times(3)
            ->andReturnUsing(static function (array $args): int {
                return match ($args['post_type'] ?? '') {
                    'llms_quiz'      => 20,
                    'llms_question'  => 30 + (int) preg_replace('/\D/', '', (string) ($args['post_title'] ?? '1')),
                    default          => 0,
                };
            });
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedQuizzes(1, lessonId: 15, options: ['questions_per_quiz' => 2]);

        $this->assertSame(20, $this->meta->getValue(31, '_llms_parent_id'));
        $this->assertSame('true_false', $this->meta->getValue(31, '_llms_question_type'));
        $this->assertSame(20, $this->meta->getValue(32, '_llms_parent_id'));
    }

    public function test_seed_users_creates_students(): void
    {
        Functions\expect('wp_create_user')->times(2)->andReturn(1, 2);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedUsers(2);

        $this->assertCount(2, $ids);
    }
}
