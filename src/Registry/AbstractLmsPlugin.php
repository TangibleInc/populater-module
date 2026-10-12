<?php
/**
 * Base LMS plugin registration.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\LmsSeedingProcess;
use Tangible\Populater\Seeding\ProcessRepository;

/**
 * Base LMS registration: subclasses set properties and hook into
 * {@see AbstractLmsPlugin::FILTER} on construction.
 */
abstract class AbstractLmsPlugin {

	/**
	 * Filter used to collect registered LMS plugins.
	 */
	public const FILTER = 'tangible-populator-lms-plugins';

	/**
	 * Plugins registered in this request.
	 *
	 * @var array<string, AbstractLmsPlugin>
	 */
	private static array $registered = array();

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Plugin main file relative to the plugins directory.
	 *
	 * @var string
	 */
	protected string $pluginFile;

	/**
	 * Seeder class name.
	 *
	 * @var class-string<AbstractSeeder>
	 */
	protected string $seederClass;

	/**
	 * Background action name.
	 *
	 * @var string
	 */
	protected string $backgroundAction;

	/**
	 * Cached entity schema.
	 *
	 * @var LmsEntitySchema|null
	 */
	private ?LmsEntitySchema $entitySchema = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register( array() );
		add_filter( self::FILTER, array( $this, 'register' ), 10, 1 );
	}

	/**
	 * Add this plugin to the registered plugins list.
	 *
	 * @param array<string, AbstractLmsPlugin> $plugins Registered plugins.
	 * @return array<string, AbstractLmsPlugin> The plugins list including this plugin.
	 */
	public function register( array $plugins ): array {
		$plugins[ $this->getSlug() ]          = $this;
		self::$registered[ $this->getSlug() ] = $this;

		return $plugins;
	}

	/**
	 * Plugins registered via {@see register()} (used when filter callbacks are not executed, e.g. unit tests).
	 *
	 * @return array<string, AbstractLmsPlugin>
	 */
	public static function getRegistered(): array {
		return self::$registered;
	}

	/**
	 * Clear the static registry.
	 *
	 * @return void
	 */
	public static function clearRegistered(): void {
		self::$registered = array();
	}

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return $this->slug;
	}

	/**
	 * Get the plugin name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the plugin main file.
	 *
	 * @return string
	 */
	public function getPluginFile(): string {
		return $this->pluginFile;
	}

	/**
	 * Get the background action name.
	 *
	 * @return string
	 */
	public function getBackgroundAction(): string {
		return $this->backgroundAction;
	}

	/**
	 * Whether the LMS plugin is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return is_plugin_active( $this->pluginFile );
	}

	/**
	 * Create the seeder for this plugin.
	 *
	 * @return AbstractSeeder
	 */
	public function createSeeder(): AbstractSeeder {
		$class = $this->seederClass;

		return new $class( $this );
	}

	/**
	 * Create the seeding process for this plugin.
	 *
	 * @param AbstractSeeder         $seeder     Seeder.
	 * @param ProcessRepository|null $repository Process repository.
	 * @return AbstractSeeding
	 */
	public function createProcess( AbstractSeeder $seeder, ?ProcessRepository $repository = null ): AbstractSeeding {
		return new LmsSeedingProcess( $seeder, $this->backgroundAction, $repository );
	}

	/**
	 * Define the entity schema for this plugin.
	 *
	 * @return LmsEntitySchema
	 */
	abstract protected function defineEntitySchema(): LmsEntitySchema;

	/**
	 * Get the (cached) entity schema.
	 *
	 * @return LmsEntitySchema
	 */
	public function getEntitySchema(): LmsEntitySchema {
		return $this->entitySchema ??= $this->defineEntitySchema();
	}
}
