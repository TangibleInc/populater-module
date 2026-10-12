<?php
/**
 * Seeded username.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Deterministic WordPress login names for seeded LMS users.
 *
 * Prefixes are plugin-specific so bench accounts are easy to spot in logs
 * (e.g. lifterstudent99, ldstudent2). Each plugin’s k6 stress test hardcodes
 * the matching student prefix (see k6/lifterlms-stress.js, k6/learndash-stress.js).
 */
final class SeededUsername {

	/**
	 * Builds the deterministic login name of a seeded user.
	 *
	 * @param string $pluginSlug Slug of the LMS plugin.
	 * @param string $roleType   Role type, such as student or groupadmin.
	 * @param int    $index      One-based index of the user.
	 * @return string Login name made of the plugin prefix and the index.
	 */
	public static function username( string $pluginSlug, string $roleType, int $index ): string {
		return self::prefix( $pluginSlug, $roleType ) . $index;
	}

	/**
	 * Builds the email address of a seeded user from its login name.
	 *
	 * @param string $pluginSlug Slug of the LMS plugin.
	 * @param string $roleType   Role type, such as student or groupadmin.
	 * @param int    $index      One-based index of the user.
	 * @return string Email address on the example.com domain.
	 */
	public static function email( string $pluginSlug, string $roleType, int $index ): string {
		return self::username( $pluginSlug, $roleType, $index ) . '@example.com';
	}

	/**
	 * Gets the login name prefix for a plugin and role type.
	 *
	 * @param string $pluginSlug Slug of the LMS plugin.
	 * @param string $roleType   Role type, such as student or groupadmin.
	 * @return string Prefix, or the role type itself for unknown plugins.
	 */
	public static function prefix( string $pluginSlug, string $roleType ): string {
		return match ( $pluginSlug ) {
			'lifterlms' => match ( $roleType ) {
				'groupadmin' => 'liftergroupadmin',
				default      => 'lifterstudent',
			},
			'learndash' => match ( $roleType ) {
				'groupadmin' => 'ldgroupadmin',
				default      => 'ldstudent',
			},
			'tangible-lms' => match ( $roleType ) {
				'groupadmin' => 'tlmsgroupadmin',
				default      => 'tlmsstudent',
			},
			default => $roleType,
		};
	}
}
