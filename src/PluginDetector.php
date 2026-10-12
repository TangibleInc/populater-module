<?php
/**
 * Active LMS plugin detection.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater;

use Tangible\Populater\Registry\LmsPluginRegistry;

/**
 * Detects which supported LMS plugins are active.
 */
class PluginDetector {

	/**
	 * Constructor.
	 *
	 * @param LmsPluginRegistry $registry Plugin registry.
	 */
	public function __construct(
		private readonly LmsPluginRegistry $registry = new LmsPluginRegistry(),
	) {}

	/**
	 * Get all supported LMS plugins with their display name and main file.
	 *
	 * @return array<string, array{name: string, file: string}> Plugin metadata keyed by slug.
	 */
	public function getSupportedPlugins(): array {
		return $this->registry->getSupportedPluginsMetadata();
	}

	/**
	 * Get only the supported LMS plugins that are currently active.
	 *
	 * @return array<string, array{name: string, file: string}> Plugin metadata keyed by slug.
	 */
	public function getActivePlugins(): array {
		return array_filter(
			$this->getSupportedPlugins(),
			fn( string $slug ) => $this->isPluginActive( $slug ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Checks whether a supported plugin is active.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True when the plugin is active.
	 * @throws \InvalidArgumentException When slug is not recognised.
	 */
	public function isPluginActive( string $slug ): bool {
		return $this->registry->isPluginActive( $slug );
	}

	/**
	 * Whether at least one supported LMS plugin is active.
	 *
	 * @return bool True when any supported plugin is active.
	 */
	public function hasAnyActivePlugin(): bool {
		return $this->registry->hasAnyActivePlugin();
	}
}
