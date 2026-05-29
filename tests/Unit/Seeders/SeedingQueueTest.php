<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedConfig;

/**
 * Cross-component queue tests (registry + seeder + queue) without LMS-specific mocks.
 */
class SeedingQueueTest extends \WPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LmsPlugins::registerBuiltIn();
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function lmsSlugProvider(): array
    {
        return [
            'learndash'    => ['learndash', 4],
            'lifterlms'    => ['lifterlms', 4],
            'tangible-lms' => ['tangible-lms', 4],
        ];
    }

    /**
     * @dataProvider lmsSlugProvider
     */
    public function test_build_seed_queue_for_one_of_each_entity(string $slug, int $expectedCount): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder($slug);
        $config   = new SeedConfig($slug, 1, 1, 1, 0, 0, 0, 0, 1);
        $queue    = $seeder->buildSeedQueue($config);

        $this->assertCount($expectedCount, $queue);

        $types = array_map(static fn($item) => $item->type, $queue);
        $this->assertContains('course', $types);
        $this->assertContains('lesson', $types);
        $this->assertContains('quiz', $types);
        $this->assertContains('user', $types);
    }

    public function test_build_seed_queue_passes_structure_settings_to_lesson_and_quiz_items(): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder('learndash');
        $config   = new SeedConfig('learndash', 1, 2, 1, 4, 3, 2, 1, 5);
        $queue    = $seeder->buildSeedQueue($config);

        $lesson = array_values(array_filter($queue, static fn($item) => $item->type === 'lesson'))[0];
        $quiz   = array_values(array_filter($queue, static fn($item) => $item->type === 'quiz'))[0];

        $this->assertSame(2, $lesson->data['lessons_per_course']);
        $this->assertSame(3, $lesson->data['topics_per_lesson']);
        $this->assertSame(2, $lesson->data['sections_per_course']);
        $this->assertSame(1, $lesson->data['modules_per_course']);
        $this->assertSame(4, $quiz->data['questions_per_quiz']);
    }
}
