<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit;

use Tangible\Populater\PluginDetector;
use Tangible\Populater\Registry\LmsPlugins;
use Brain\Monkey\Functions;

class PluginDetectorTest extends \WPTestCase
{
    private PluginDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        LmsPlugins::registerBuiltIn();
        $this->detector = new PluginDetector();
    }

    public function test_get_supported_plugins_returns_all_three_lms(): void
    {
        $plugins = $this->detector->getSupportedPlugins();

        $this->assertArrayHasKey('learndash', $plugins);
        $this->assertArrayHasKey('lifterlms', $plugins);
        $this->assertArrayHasKey('tangible-lms', $plugins);
    }

    public function test_get_active_plugins_returns_only_active_ones(): void
    {
        Functions\when('is_plugin_active')
            ->alias(fn(string $plugin) => $plugin === 'sfwd-lms/sfwd_lms.php');

        $active = $this->detector->getActivePlugins();

        $this->assertCount(1, $active);
        $this->assertArrayHasKey('learndash', $active);
    }

    public function test_is_plugin_active_returns_true_for_active_plugin(): void
    {
        Functions\when('is_plugin_active')->justReturn(true);

        $this->assertTrue($this->detector->isPluginActive('learndash'));
    }

    public function test_is_plugin_active_returns_false_for_inactive_plugin(): void
    {
        Functions\when('is_plugin_active')->justReturn(false);

        $this->assertFalse($this->detector->isPluginActive('learndash'));
    }

    public function test_is_plugin_active_throws_for_unknown_plugin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->detector->isPluginActive('unknown-lms');
    }

    public function test_has_any_active_plugin_returns_true_when_at_least_one_active(): void
    {
        Functions\when('is_plugin_active')
            ->alias(fn(string $plugin) => $plugin === 'sfwd-lms/sfwd_lms.php');

        $this->assertTrue($this->detector->hasAnyActivePlugin());
    }

    public function test_has_any_active_plugin_returns_false_when_none_active(): void
    {
        Functions\when('is_plugin_active')->justReturn(false);

        $this->assertFalse($this->detector->hasAnyActivePlugin());
    }
}
