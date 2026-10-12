<?php
/**
 * Seeded user profile.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Fills WordPress user profile fields for seeded accounts.
 */
final class SeededUserProfile {

	/**
	 * Sets the first name, last name and display name of a seeded user.
	 *
	 * @param int    $userId   ID of the user to update.
	 * @param int    $index    One-based index of the user.
	 * @param string $roleType Role type, either student or groupadmin.
	 * @return void
	 */
	public static function populate( int $userId, int $index, string $roleType = 'student' ): void {
		if ( $userId <= 0 || ! function_exists( 'update_user_meta' ) ) {
			return;
		}

		[$firstName, $lastName, $displayName] = self::names( $index, $roleType );

		update_user_meta( $userId, 'first_name', $firstName );
		update_user_meta( $userId, 'last_name', $lastName );

		if ( function_exists( 'wp_update_user' ) ) {
			wp_update_user(
				array(
					'ID'           => $userId,
					'display_name' => $displayName,
				)
			);
		}
	}

	/**
	 * Builds the first name, last name and display name for a seeded user.
	 *
	 * @param int    $index    One-based index of the user.
	 * @param string $roleType Role type, either student or groupadmin.
	 * @return array{0: string, 1: string, 2: string} First name, last name and display name.
	 */
	private static function names( int $index, string $roleType ): array {
		if ( 'groupadmin' === $roleType ) {
			return array(
				'Group',
				"Admin {$index}",
				"Group Admin {$index}",
			);
		}

		return array(
			'Student',
			(string) $index,
			"Student {$index}",
		);
	}
}
