<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Registry;

use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeedingProcess;
use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;

class LmsPluginRegistryTest extends \WPTestCase
{
    private LmsPluginRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new LmsPluginRegistry();
    }

    public function test_all_returns_three_lms_plugins(): void
    {
        $this->assertCount(3, $this->registry->all());
        $this->assertArrayHasKey('learndash', $this->registry->all());
        $this->assertArrayHasKey('lifterlms', $this->registry->all());
        $this->assertArrayHasKey('tangible-lms', $this->registry->all());
    }

    public function test_get_throws_for_unknown_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->get('unknown');
    }

    public function test_create_seeder_returns_abstract_seeder(): void
    {
        $seeder = $this->registry->createSeeder('learndash');

        $this->assertInstanceOf(LearnDashSeeder::class, $seeder);
        $this->assertInstanceOf(AbstractSeeder::class, $seeder);
        $this->assertSame('learndash', $seeder->getSlug());
    }

    public function test_create_process_returns_seeding_process(): void
    {
        $seeder  = $this->registry->createSeeder('lifterlms');
        $process = $this->registry->createProcess('lifterlms', $seeder);

        $this->assertInstanceOf(LifterLMSSeedingProcess::class, $process);
        $this->assertInstanceOf(AbstractSeeding::class, $process);
    }

    public function test_supported_metadata_matches_slugs(): void
    {
        $metadata = $this->registry->getSupportedPluginsMetadata();

        $this->assertSame('LearnDash LMS', $metadata['learndash']['name']);
        $this->assertSame('sfwd-lms/sfwd_lms.php', $metadata['learndash']['file']);
    }
}
