<?php
/**
 * Lifter Lms Enrollment Setup.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

/**
 * Seeds LifterLMS enrollment prerequisites on empty sites: checkout page and free access plans.
 */
final class LifterLmsEnrollmentSetup {

	private const CHECKOUT_OPTION  = 'lifterlms_checkout_page_id';
	private const CHECKOUT_SLUG    = 'purchase';
	private const CHECKOUT_TITLE   = 'Purchase';
	private const CHECKOUT_CONTENT = '[lifterlms_checkout]';

	/**
	 * Ensures a checkout page exists and adds a free access plan to a course.
	 *
	 * @param int $courseId Course post ID.
	 * @param int $index One-based course index.
	 * @return int The access plan post ID, or 0 when none was created.
	 */
	public static function seedCourseEnrollment( int $courseId, int $index = 1 ): int {
		if ( $courseId <= 0 ) {
			return 0;
		}

		self::ensureCheckoutPage();

		return self::createFreeAccessPlan( $courseId, $index );
	}

	/**
	 * Returns the LifterLMS checkout page ID, creating the page when it is missing.
	 *
	 * @return int The checkout page ID, or 0 when it could not be created.
	 */
	public static function ensureCheckoutPage(): int {
		$existingId = (int) get_option( self::CHECKOUT_OPTION, 0 );

		if ( $existingId > 0 && \get_post_status( $existingId ) ) {

			return $existingId;
		}

		if ( function_exists( 'llms_create_page' ) ) {
			$pageId = (int) llms_create_page(
				self::CHECKOUT_SLUG,
				self::CHECKOUT_TITLE,
				self::CHECKOUT_CONTENT,
				self::CHECKOUT_OPTION,
			);

			if ( $pageId > 0 ) {

				return $pageId;
			}
		}

		$pageId = wp_insert_post(
			array(
				'post_title'   => self::CHECKOUT_TITLE,
				'post_name'    => self::CHECKOUT_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => self::CHECKOUT_CONTENT,
			)
		);

		if ( is_int( $pageId ) && $pageId > 0 ) {
			update_option( self::CHECKOUT_OPTION, $pageId );
		}

		return is_int( $pageId ) ? $pageId : 0;
	}

	/**
	 * Creates a lifetime free access plan for a course.
	 *
	 * @param int $courseId Course post ID.
	 * @param int $index One-based course index.
	 * @return int The access plan post ID, or 0 on failure.
	 */
	public static function createFreeAccessPlan( int $courseId, int $index = 1 ): int {
		if ( function_exists( 'llms_insert_access_plan' ) ) {
			$plan = llms_insert_access_plan(
				array(
					'product_id'        => $courseId,
					'title'             => sprintf( 'Free Access %d', $index ),
					'is_free'           => 'yes',
					'price'             => 0,
					'frequency'         => 0,
					'access_expiration' => 'lifetime',
					'availability'      => 'open',
					'visibility'        => 'visible',
					'enroll_text'       => 'Enroll Now',
					'menu_order'        => $index,
				)
			);

			if ( $plan instanceof \LLMS_Access_Plan ) {
				return (int) $plan->get( 'id' );
			}
		}

		$planId = wp_insert_post(
			array(
				'post_title'  => sprintf( 'Free Access %d', $index ),
				'post_type'   => 'llms_access_plan',
				'post_status' => 'publish',
				'menu_order'  => $index,
			)
		);

		if ( ! is_int( $planId ) || $planId <= 0 ) {
			return 0;
		}

		$meta = array(
			'_llms_product_id'               => $courseId,
			'_llms_is_free'                  => 'yes',
			'_llms_price'                    => 0,
			'_llms_frequency'                => 0,
			'_llms_access_expiration'        => 'lifetime',
			'_llms_availability'             => 'open',
			'_llms_on_sale'                  => 'no',
			'_llms_trial_offer'              => 'no',
			'_llms_checkout_redirect_forced' => 'no',
			'_llms_checkout_redirect_type'   => 'self',
			'_llms_enroll_text'              => 'Enroll Now',
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $planId, $key, $value );
		}

		if ( taxonomy_exists( 'llms_access_plan_visibility' ) ) {
			wp_set_object_terms( $planId, 'visible', 'llms_access_plan_visibility', false );
		}

		return $planId;
	}
}
