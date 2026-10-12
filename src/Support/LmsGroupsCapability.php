<?php
/**
 * LMS groups capability.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Whether an LMS supports seeding groups for the admin UI and queue builder.
 */
final class LmsGroupsCapability {

	/**
	 * Checks whether an LMS supports seeding groups.
	 *
	 * @param string $pluginSlug Slug of the LMS plugin.
	 * @return bool True if groups can be seeded for the plugin.
	 */
	public static function supports( string $pluginSlug ): bool {
		return match ( $pluginSlug ) {
			'learndash' => true,
			'lifterlms' => self::lifterGroupsAddonActive(),
			default     => false,
		};
	}

	/**
	 * Checks whether the LifterLMS Groups add-on is loaded.
	 *
	 * @return bool True if the add-on is active.
	 */
	public static function lifterGroupsAddonActive(): bool {
		return function_exists( 'llms_groups' )
			|| class_exists( 'LLMS_Groups_Enrollment', false );
	}
}
