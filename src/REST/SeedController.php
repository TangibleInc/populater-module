<?php
/**
 * Seed controller.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\REST;

use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedingManager;

/**
 * REST controller for seeding operations.
 *
 * Endpoints (all require manage_options):
 *   POST   /tangible-populater/v1/seed                         – start a seeding process
 *   GET    /tangible-populater/v1/seed/active                  – get active seeding process
 *   GET    /tangible-populater/v1/seed/{id}/status             – get status
 *   GET    /tangible-populater/v1/seed/{id}/logs               – get logs
 *   POST   /tangible-populater/v1/seed/{id}/cancel             – cancel
 *   GET    /tangible-populater/v1/plugins                      – list supported plugins
 */
class SeedController {

	private const NAMESPACE = 'tangible-populater/v1';

	/**
	 * Creates the controller.
	 *
	 * @param SeedingManager $manager Manager used to start, inspect and cancel seeding processes.
	 */
	public function __construct( private readonly SeedingManager $manager ) {}

	/**
	 * Registers the seeding, status, logs, cancel and plugin listing REST routes.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/seed',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'startSeed' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
				'args'                => $this->getSeedArgs(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/seed/active',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getActiveSeed' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
			)
		);

		// Legacy route kept for older admin.js builds that polled user seeding separately.
		register_rest_route(
			self::NAMESPACE,
			'/users/active',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getLegacyActiveUserSeed' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/seed/(?P<id>[\w-]+)/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getStatus' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/seed/(?P<id>[\w-]+)/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getLogs' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/seed/(?P<id>[\w-]+)/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancelSeed' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/plugins',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listPlugins' ),
				'permission_callback' => array( $this, 'checkAdminPermission' ),
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
	 * Starts a seeding process from the request parameters.
	 *
	 * @param \WP_REST_Request $request Request carrying the seeding configuration.
	 * @return \WP_REST_Response|\WP_Error Response with the process ID, or an error if the plugin is invalid or inactive.
	 */
	public function startSeed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$config = array(
			'plugin'              => $request->get_param( 'plugin' ),
			'courses'             => (int) ( $request->get_param( 'courses' ) ?? SeedConfig::DEFAULT_COURSES ),
			'lessons_per_course'  => (int) ( $request->get_param( 'lessons_per_course' ) ?? SeedConfig::DEFAULT_LESSONS_PER_COURSE ),
			'quizzes_per_section' => (int) ( $request->get_param( 'quizzes_per_section' ) ?? SeedConfig::DEFAULT_QUIZZES_PER_SECTION ),
			'quizzes_per_lesson'  => (int) ( $request->get_param( 'quizzes_per_lesson' ) ?? 0 ),
			'questions_per_quiz'  => (int) ( $request->get_param( 'questions_per_quiz' ) ?? SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ ),
			'topics_per_lesson'   => (int) ( $request->get_param( 'topics_per_lesson' ) ?? SeedConfig::DEFAULT_TOPICS_PER_LESSON ),
			'sections_per_course' => (int) ( $request->get_param( 'sections_per_course' ) ?? SeedConfig::DEFAULT_SECTIONS_PER_COURSE ),
			'lessons_per_section' => (int) ( $request->get_param( 'lessons_per_section' ) ?? 0 ),
			'modules_per_course'  => (int) ( $request->get_param( 'modules_per_course' ) ?? SeedConfig::DEFAULT_MODULES_PER_COURSE ),
			'users'               => (int) ( $request->get_param( 'users' ) ?? SeedConfig::DEFAULT_USERS ),
			'groups'              => (int) ( $request->get_param( 'groups' ) ?? SeedConfig::DEFAULT_GROUPS ),
			'user_password'       => $request->get_param( 'user_password' ),
			'complete_courses'    => (bool) ( $request->get_param( 'complete_courses' ) ?? false ),
		);

		try {
			$processId = $this->manager->start( $config );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'invalid_plugin', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'plugin_inactive', $e->getMessage(), array( 'status' => 422 ) );
		}

		return rest_ensure_response(
			array(
				'process_id' => $processId,
				'message'    => __( 'Seeding process started.', 'tangible-populater' ),
			)
		);
	}

	/**
	 * Returns the currently active seeding process, if any.
	 *
	 * @param \WP_REST_Request $request Unused request.
	 * @return \WP_REST_Response Response with a "process" key holding the status array or null.
	 */
	public function getActiveSeed( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the REST callback signature.
		$active = $this->manager->getActiveProcess();

		return rest_ensure_response(
			array(
				'process' => $active?->toArray(),
			)
		);
	}

	/**
	 * Returns an empty active process for older admin scripts that polled user seeding separately.
	 *
	 * @param \WP_REST_Request $request Unused request.
	 * @return \WP_REST_Response Response whose "process" key is always null.
	 */
	public function getLegacyActiveUserSeed( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the REST callback signature.
		return rest_ensure_response(
			array(
				'process' => null,
			)
		);
	}

	/**
	 * Returns the status of a seeding process.
	 *
	 * @param \WP_REST_Request $request Request carrying the process "id".
	 * @return \WP_REST_Response Response with the status data.
	 */
	public function getStatus( \WP_REST_Request $request ): \WP_REST_Response {
		$processId = (string) $request->get_param( 'id' );
		$status    = $this->manager->getStatus( $processId );

		return rest_ensure_response( $status->toArray() );
	}

	/**
	 * Returns the log entries of a seeding process.
	 *
	 * @param \WP_REST_Request $request Request carrying the process "id".
	 * @return \WP_REST_Response Response with a "logs" key holding the entries.
	 */
	public function getLogs( \WP_REST_Request $request ): \WP_REST_Response {
		$processId = (string) $request->get_param( 'id' );

		return rest_ensure_response(
			array(
				'logs' => $this->manager->getLogs( $processId ),
			)
		);
	}

	/**
	 * Cancels a seeding process.
	 *
	 * @param \WP_REST_Request $request Request carrying the process "id".
	 * @return \WP_REST_Response|\WP_Error Response confirming the cancellation, or a 409 error if it cannot be cancelled.
	 */
	public function cancelSeed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$processId = (string) $request->get_param( 'id' );
		$cancelled = $this->manager->cancel( $processId );

		if ( ! $cancelled ) {
			return new \WP_Error(
				'cannot_cancel',
				__( 'Process cannot be cancelled (already completed, failed, or not found).', 'tangible-populater' ),
				array( 'status' => 409 )
			);
		}

		return rest_ensure_response( array( 'cancelled' => true ) );
	}

	/**
	 * Lists the supported LMS plugins and whether each is active.
	 *
	 * @param \WP_REST_Request $request Unused request.
	 * @return \WP_REST_Response Response with a "plugins" key holding the plugin list.
	 */
	public function listPlugins( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the REST callback signature.
		return rest_ensure_response(
			array(
				'plugins' => $this->manager->getSupportedPlugins(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds the argument schema for the seed route.
	 *
	 * @return array<string, array<string, mixed>> Argument definitions keyed by parameter name.
	 */
	private function getSeedArgs(): array {
		return array(
			'plugin'              => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'courses'             => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_COURSES,
				'minimum' => 0,
				'maximum' => 100000,
			),
			'lessons_per_course'  => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_LESSONS_PER_COURSE,
				'minimum' => 0,
				'maximum' => 100,
			),
			'quizzes_per_section' => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_QUIZZES_PER_SECTION,
				'minimum' => 0,
				'maximum' => 50,
			),
			'quizzes_per_lesson'  => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
				'maximum' => 50,
			),
			'questions_per_quiz'  => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
				'minimum' => 0,
				'maximum' => 100,
			),
			'topics_per_lesson'   => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_TOPICS_PER_LESSON,
				'minimum' => 0,
				'maximum' => 50,
			),
			'sections_per_course' => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_SECTIONS_PER_COURSE,
				'minimum' => 0,
				'maximum' => 50,
			),
			'lessons_per_section' => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
				'maximum' => 100,
			),
			'modules_per_course'  => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_MODULES_PER_COURSE,
				'minimum' => 0,
				'maximum' => 50,
			),
			'users'               => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_USERS,
				'minimum' => 0,
				'maximum' => 100000,
			),
			'groups'              => array(
				'type'    => 'integer',
				'default' => SeedConfig::DEFAULT_GROUPS,
				'minimum' => 0,
				'maximum' => 100,
			),
			'user_password'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'complete_courses'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}
}
