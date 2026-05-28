<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Registry;

use Tangible\Populater\LMS\LearnDash\LearnDashLmsPlugin;
use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Tangible\Populater\LMS\LifterLMS\LifterLmsPlugin;
use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\LmsSeedingProcess;

class LmsPluginRegistryTest extends \WPTestCase
{
    private LmsPluginRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        LmsPlugins::registerBuiltIn();
        $this->registry = new LmsPluginRegistry();
    }

    public function test_all_returns_plugins_from_filter(): void
    {
        $this->assertCount(3, $this->registry->all());
        $this->assertArrayHasKey('learndash', $this->registry->all());
        $this->assertInstanceOf(LearnDashLmsPlugin::class, $this->registry->get('learndash'));
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

        $this->assertInstanceOf(LmsSeedingProcess::class, $process);
        $this->assertInstanceOf(AbstractSeeding::class, $process);
    }

    public function test_supported_metadata_matches_slugs(): void
    {
        $metadata = $this->registry->getSupportedPluginsMetadata();

        $this->assertSame('LearnDash LMS', $metadata['learndash']['name']);
        $this->assertSame('sfwd-lms/sfwd_lms.php', $metadata['learndash']['file']);
    }

    public function test_external_plugin_can_register_itself(): void
    {
        $custom = new CustomLmsPluginForTest();

        $this->assertArrayHasKey('custom-lms', $this->registry->all());
        $this->assertSame('Custom LMS', $this->registry->get('custom-lms')->getName());
    }
}

/**
 * Named class so Brain Monkey can register the filter callback.
 */
final class CustomLmsPluginForTest extends \Tangible\Populater\Registry\AbstractLmsPlugin
{
    protected string $slug = 'custom-lms';
    protected string $name = 'Custom LMS';
    protected string $pluginFile = 'custom/custom.php';
    protected string $seederClass = LearnDashSeeder::class;
    protected string $backgroundAction = 'seed_custom';
}
