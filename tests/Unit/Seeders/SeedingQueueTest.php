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
        $config   = new SeedConfig($slug, 1, 1, 1, 1);
        $queue    = $seeder->buildSeedQueue($config);

        $this->assertCount($expectedCount, $queue);

        $types = array_map(static fn($item) => $item->type, $queue);
        $this->assertContains('course', $types);
        $this->assertContains('lesson', $types);
        $this->assertContains('quiz', $types);
        $this->assertContains('user', $types);
    }
}
