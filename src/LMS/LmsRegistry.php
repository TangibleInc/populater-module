<?php
/**
 * Lms Registry.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS;

/**
 * Registry for LMS-specific configurations and rendering.
 */
class LmsRegistry {

	/**
	 * Get all LMS settings tabs registered through the filter.
	 *
	 * @return array<string, \Tangible\Populater\LMS\LmsSettingsTab> Tabs keyed by slug.
	 */
	public static function getTabs(): array {
		return apply_filters( 'tangible_populater_lms_tabs', array() );
	}

	/**
	 * Get a specific LMS tab by slug.
	 *
	 * @param string $slug LMS tab slug.
	 * @return \Tangible\Populater\LMS\LmsSettingsTab|null The tab, or null when none is registered for the slug.
	 */
	public static function getTab( string $slug ): ?\Tangible\Populater\LMS\LmsSettingsTab {
		$tabs = self::getTabs();
		return $tabs[ $slug ] ?? null;
	}

	/**
	 * Get the label and description of every registered LMS tab, keyed by slug.
	 *
	 * @return array<string, array{label: string, description: string}> Tab summaries keyed by tab slug.
	 * @deprecated Use getTabs() and LmsSettingsTab methods instead.
	 */
	public static function getPlugins(): array {
		$plugins = array();
		foreach ( self::getTabs() as $slug => $tab ) {
			$plugins[ $slug ] = array(
				'label'       => $tab->getLabel(),
				'description' => $tab->getDescription(),
			);
		}
		return $plugins;
	}

	/**
	 * Get the default configuration of an LMS tab, or an empty array when the tab is unknown.
	 *
	 * @param string $slug LMS tab slug.
	 * @return array<string, mixed> Default configuration values.
	 * @deprecated Use getTab($slug)->getDefaultConfig() instead.
	 */
	public static function getDefaultConfig( string $slug ): array {
		$tab = self::getTab( $slug );
		return $tab ? $tab->getDefaultConfig() : array();
	}

	/**
	 * Get a callback that renders the settings fields of an LMS tab.
	 *
	 * @param string $slug LMS tab slug.
	 * @return callable|null The renderer, or null when the tab is unknown.
	 * @deprecated Use getTab($slug)->renderFields() instead.
	 */
	public static function getFieldsRenderer( string $slug ): ?callable {
		$tab = self::getTab( $slug );
		if ( ! $tab ) {
			return null;
		}
		return fn() => $tab->renderFields();
	}

	/**
	 * Whether an LMS tab supports groups.
	 *
	 * @param string $slug LMS tab slug.
	 * @return bool True when the tab exists and supports groups.
	 * @deprecated Use getTab($slug)->supportsGroups() instead.
	 */
	public static function supportsGroups( string $slug ): bool {
		$tab = self::getTab( $slug );
		return $tab ? $tab->supportsGroups() : false;
	}
}
