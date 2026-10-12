<?php
/**
 * Lms Settings Tab.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS;

/**
 * Abstract class for LMS settings tabs.
 * Third-party developers can extend this to add support for new LMS plugins.
 */
abstract class LmsSettingsTab {

	/**
	 * Returns the unique slug for the LMS (e.g. "learndash").
	 *
	 * @return string The LMS slug.
	 */
	abstract public function getSlug(): string;

	/**
	 * Returns the display label for the settings tab.
	 *
	 * @return string The translated tab label.
	 */
	abstract public function getLabel(): string;

	/**
	 * Returns a short description of the LMS content structure.
	 *
	 * @return string The translated structure description.
	 */
	abstract public function getDescription(): string;

	/**
	 * Returns the default seeding configuration for this LMS.
	 *
	 * @return array<string, mixed> Default seeding counts keyed by SeedConfig field name.
	 */
	abstract public function getDefaultConfig(): array;

	/**
	 * Tells whether this LMS supports groups.
	 *
	 * @return bool True when groups can be seeded for this LMS.
	 */
	abstract public function supportsGroups(): bool;

	/**
	 * Renders the LMS-specific field rows on the settings page.
	 *
	 * @return void
	 */
	abstract public function renderFields(): void;
}
