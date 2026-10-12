<?php
/**
 * Resolves registered LMS plugins from the filter hook.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\ProcessRepository;

/**
 * Resolves registered LMS plugins from the {@see AbstractLmsPlugin::FILTER} hook.
 */
class LmsPluginRegistry {

	/**
	 * All registered plugins.
	 *
	 * @return array<string, AbstractLmsPlugin>
	 */
	public function all(): array {
		/**
		 * Registered plugins.
		 *
		 * @var array<string, AbstractLmsPlugin> $plugins
		 */
		$plugins = apply_filters( AbstractLmsPlugin::FILTER, array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook name is a prefixed class constant.

		if ( array() !== $plugins ) {
			return $plugins;
		}

		return AbstractLmsPlugin::getRegistered();
	}

	/**
	 * Slugs of all registered plugins.
	 *
	 * @return list<string>
	 */
	public function slugs(): array {
		return array_keys( $this->all() );
	}

	/**
	 * Get a registered plugin by slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return AbstractLmsPlugin
	 * @throws \InvalidArgumentException When the slug is unknown.
	 */
	public function get( string $slug ): AbstractLmsPlugin {
		$plugins = $this->all();

		if ( ! isset( $plugins[ $slug ] ) ) {
			throw new \InvalidArgumentException(
				esc_html( sprintf( 'Unknown plugin slug "%s". Supported: %s', $slug, implode( ', ', array_keys( $plugins ) ) ) )
			);
		}

		return $plugins[ $slug ];
	}

	/**
	 * Whether a plugin is registered.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	public function has( string $slug ): bool {
		return isset( $this->all()[ $slug ] );
	}

	/**
	 * Name and file metadata for each registered plugin.
	 *
	 * @return array<string, array{name: string, file: string}>
	 */
	public function getSupportedPluginsMetadata(): array {
		$plugins = array();

		foreach ( $this->all() as $slug => $plugin ) {
			$plugins[ $slug ] = array(
				'name' => $plugin->getName(),
				'file' => $plugin->getPluginFile(),
			);
		}

		return $plugins;
	}

	/**
	 * Whether a registered plugin is active.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	public function isPluginActive( string $slug ): bool {
		return $this->get( $slug )->isActive();
	}

	/**
	 * Whether any registered plugin is active.
	 *
	 * @return bool
	 */
	public function hasAnyActivePlugin(): bool {
		foreach ( $this->slugs() as $slug ) {
			if ( $this->isPluginActive( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create a seeder for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return AbstractSeeder
	 */
	public function createSeeder( string $slug ): AbstractSeeder {
		return $this->get( $slug )->createSeeder();
	}

	/**
	 * Create a seeding process for a plugin.
	 *
	 * @param string                 $slug       Plugin slug.
	 * @param AbstractSeeder         $seeder     Seeder.
	 * @param ProcessRepository|null $repository Process repository.
	 * @return AbstractSeeding
	 */
	public function createProcess( string $slug, AbstractSeeder $seeder, ?ProcessRepository $repository = null ): AbstractSeeding {
		return $this->get( $slug )->createProcess( $seeder, $repository );
	}
}
