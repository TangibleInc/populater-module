<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Brain\Monkey\Functions;

class SeedingIdMapTest extends \WPTestCase
{
    private ProcessRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProcessRepository();
        Functions\when('get_option')->justReturn(null);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
    }

    public function test_enrich_resolves_lesson_course_id(): void
    {
        Functions\when('get_option')->justReturn([
            'courses' => [1 => 42],
            'lessons' => [],
        ]);

        $data = SeedingIdMap::enrich('proc-1', 'lesson', ['course_index' => 1, 'index' => 1], $this->repository);

        $this->assertSame(42, $data['course_id']);
    }

    public function test_enrich_resolves_quiz_topic_id(): void
    {
        Functions\when('get_option')->justReturn([
            'courses' => [2 => 55],
            'lessons' => [2 => [3 => 99]],
            'topics'  => [2 => [3 => [1 => 777]]],
        ]);

        $data = SeedingIdMap::enrich('proc-1', 'quiz', [
            'course_index'       => 2,
            'lesson_index'       => 3,
            'quiz_parent_index'  => 1,
            'quiz_parent_entity' => 'topics',
            'index'              => 1,
        ], $this->repository);

        $this->assertSame(777, $data['quiz_parent_id']);
        $this->assertSame(99, $data['lesson_id']);
        $this->assertSame(55, $data['course_id']);
    }

    public function test_enrich_resolves_quiz_section_lesson_id(): void
    {
        Functions\when('get_option')->justReturn([
            'courses' => [1 => 10],
            'sections' => [1 => [1 => 88]],
            'section_lessons' => [1 => [1 => [11, 12, 13]]],
        ]);

        $data = SeedingIdMap::enrich('proc-1', 'quiz', [
            'course_index'       => 1,
            'quiz_parent_index'  => 1,
            'quiz_parent_entity' => 'sections',
            'index'              => 1,
        ], $this->repository);

        $this->assertSame(88, $data['quiz_parent_id']);
        $this->assertSame(13, $data['lesson_id']);
    }

    public function test_record_then_enrich_lesson_uses_stored_course(): void
    {
        $stored = ['courses' => [], 'lessons' => []];

        Functions\when('get_option')->alias(
            static function (string $key) use (&$stored) {
                return $key === 'tangible_populater_ids_proc-1' ? $stored : null;
            }
        );
        Functions\when('update_option')->alias(
            static function (string $key, mixed $value) use (&$stored): bool {
                if ($key === 'tangible_populater_ids_proc-1' && is_array($value)) {
                    $stored = $value;
                }

                return true;
            }
        );

        SeedingIdMap::record('proc-1', 'course', ['index' => 1], [10], $this->repository);

        $data = SeedingIdMap::enrich('proc-1', 'lesson', ['course_index' => 1, 'index' => 1], $this->repository);

        $this->assertSame(10, $data['course_id']);
    }
}
