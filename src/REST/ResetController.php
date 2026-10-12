<?php
/**
 * Reset controller.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\REST;

use Tangible\Populater\Database\DatabaseReset;

/**
 * REST controller for database reset.
 *
 * Endpoint (requires manage_options):
 *   POST /tangible-populater/v1/reset   – remove seeded content while preserving site config
 */
class ResetController {

	private const NAMESPACE = 'tangible-populater/v1';

	/**
	 * Creates the controller.
	 *
	 * @param DatabaseReset $dbReset Service that performs the database reset.
	 */
	public function __construct( private readonly DatabaseReset $dbReset ) {}

	/**
	 * Registers the reset REST route.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resetDatabase' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
				'args'                => array(
					'confirmed' => array(
						'required'    => true,
						'type'        => 'boolean',
						'description' => 'Must be true to confirm the destructive operation.',
					),
				),
			)
		);
	}

	/**
	 * Checks that the current user may manage options.
	 *
	 * @return bool True if the current user has the manage_options capability.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Resets the database if the environment allows it and the request is confirmed.
	 *
	 * @param \WP_REST_Request $request Request carrying the "confirmed" parameter.
	 * @return \WP_REST_Response|\WP_Error Response with the success flag and a message.
	 */
	public function resetDatabase( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->dbReset->isSafeEnvironment() ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Database reset is disabled on this site.', 'tangible-populater' ),
				)
			);
		}

		$confirmed = (bool) $request->get_param( 'confirmed' );
		$success   = $this->dbReset->reset( confirmed: $confirmed );

		return rest_ensure_response(
			array(
				'success' => $success,
				'message' => $success
					? __( 'Seeded content has been removed. Administrator accounts, plugins, and theme were preserved.', 'tangible-populater' )
					: __( 'Reset aborted. Pass confirmed=true to proceed.', 'tangible-populater' ),
			)
		);
	}
}
