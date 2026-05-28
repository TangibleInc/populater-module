<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Database;

use Tangible\Populater\Database\WordPressCoreOptions;

class WordPressCoreOptionsTest extends \WPTestCase
{
    public function test_preserves_core_site_options(): void
    {
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('siteurl'));
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('active_plugins'));
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('template'));
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('stylesheet'));
    }

    public function test_preserves_theme_and_widget_options(): void
    {
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('theme_mods_twentytwentyfour'));
        $this->assertTrue(WordPressCoreOptions::shouldPreserve('widget_block'));
    }

    public function test_deletes_plugin_and_seeding_options(): void
    {
        $this->assertFalse(WordPressCoreOptions::shouldPreserve('learndash_settings'));
        $this->assertFalse(WordPressCoreOptions::shouldPreserve('tangible_populater_status_abc123'));
        $this->assertFalse(WordPressCoreOptions::shouldPreserve('_transient_timeout_foo'));
        $this->assertFalse(WordPressCoreOptions::shouldPreserve('seed_learndash_batch_123'));
    }
}
