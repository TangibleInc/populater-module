<?php
/**
 * Lifter Lms Student Profile.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Support\SeededUserProfile;

/**
 * Pre-fills LifterLMS user information so checkout / free enrollment forms are satisfied.
 */
final class LifterLmsStudentProfile {

	/**
	 * Fills the seeded user profile and LifterLMS billing details.
	 *
	 * @param int    $userId User ID.
	 * @param int    $index One-based user index.
	 * @param string $roleType Role type of the seeded user.
	 * @return void
	 */
	public static function populate( int $userId, int $index, string $roleType = 'student' ): void {
		SeededUserProfile::populate( $userId, $index, $roleType );

		if ( $userId <= 0 || ! function_exists( 'update_user_meta' ) ) {
			return;
		}

		foreach ( self::billingMeta( $index ) as $key => $value ) {
			update_user_meta( $userId, $key, $value );
		}
	}

	/**
	 * Builds placeholder billing address meta for a seeded user.
	 *
	 * @param int $index One-based user index.
	 * @return array<string, string> Billing meta values keyed by meta key.
	 */
	private static function billingMeta( int $index ): array {
		return array(
			'llms_billing_address_1' => "{$index} Populater Lane",
			'llms_billing_city'      => 'Testville',
			'llms_billing_state'     => 'CA',
			'llms_billing_zip'       => '90210',
			'llms_billing_country'   => 'US',
		);
	}
}
