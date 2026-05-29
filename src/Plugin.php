<?php

declare(strict_types=1);

namespace Tangible\Populater;

use Tangible\Populater\Admin\SettingsPage;
use Tangible\Populater\CLI\ResetCommand;
use Tangible\Populater\CLI\SeedCommand;
use Tangible\Populater\Database\DatabaseReset;
use Tangible\Populater\REST\ResetController;
use Tangible\Populater\REST\SeedController;
use Tangible\Populater\LMS\LifterLMS\LifterLmsCourseRewriteFix;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedingManager;

/**
 * Main plugin bootstrap class.
 */
class Plugin
{
    private static ?self $instance = null;

    private SeedingManager $seedingManager;
    private DatabaseReset $databaseReset;
    private PluginDetector $pluginDetector;

    private function __construct()
    {
        LmsPlugins::registerBuiltIn();

        $this->pluginDetector = new PluginDetector();
        $this->seedingManager  = new SeedingManager($this->pluginDetector);
        $this->databaseReset   = new DatabaseReset();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        $this->seedingManager->registerBackgroundProcesses();
        LifterLmsCourseRewriteFix::register();

        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCliCommands();
        }
    }

    public function registerAdminMenu(): void
    {
        $page = new SettingsPage($this->seedingManager, $this->databaseReset, $this->pluginDetector);
        $page->register();
    }

    public function registerRestRoutes(): void
    {
        (new SeedController($this->seedingManager))->registerRoutes();
        (new ResetController($this->databaseReset))->registerRoutes();
    }

    private function registerCliCommands(): void
    {
        \WP_CLI::add_command('tangible-populater', new SeedCommand($this->seedingManager));
        \WP_CLI::add_command('tangible-populater reset',  new ResetCommand($this->databaseReset));
    }
}
