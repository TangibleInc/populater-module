<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater;

use Tangible\Populater\Admin\SettingsPage;
use Tangible\Populater\CLI\ResetCommand;
use Tangible\Populater\CLI\SeedCommand;
use Tangible\Populater\Database\DatabaseReset;
use Tangible\Populater\REST\ResetController;
use Tangible\Populater\REST\SeedController;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedingManager;

/**
 * Main plugin bootstrap class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Seeding manager.
	 *
	 * @var SeedingManager
	 */
	private SeedingManager $seedingManager;
	/**
	 * Database reset service.
	 *
	 * @var DatabaseReset
	 */
	private DatabaseReset $databaseReset;
	/**
	 * Plugin detector.
	 *
	 * @var PluginDetector
	 */
	private PluginDetector $pluginDetector;

	/**
	 * Constructor.
	 */
	private function __construct() {
		LmsPlugins::registerBuiltIn();

		$this->pluginDetector = new PluginDetector();
		$this->seedingManager = new SeedingManager();
		$this->databaseReset  = new DatabaseReset();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->seedingManager->registerBackgroundProcesses();

		add_action( 'admin_menu', array( $this, 'registerAdminMenu' ) );
		add_action( 'rest_api_init', array( $this, 'registerRestRoutes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->registerCliCommands();
		}
	}

	/**
	 * Register the admin menu page.
	 *
	 * @return void
	 */
	public function registerAdminMenu(): void {
		$page = new SettingsPage( $this->seedingManager, $this->databaseReset, $this->pluginDetector );
		$page->register();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function registerRestRoutes(): void {
		( new SeedController( $this->seedingManager ) )->registerRoutes();
		( new ResetController( $this->databaseReset ) )->registerRoutes();
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	private function registerCliCommands(): void {
		\WP_CLI::add_command( 'tangible-populater', new SeedCommand( $this->seedingManager ) );
		\WP_CLI::add_command( 'tangible-populater reset', new ResetCommand( $this->databaseReset ) );
	}
}
