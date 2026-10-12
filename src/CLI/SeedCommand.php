<?php
/**
 * Seed command.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\CLI;

use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;

/**
 * WP-CLI commands for seeding content.
 *
 * Usage:
 *   wp tangible-populater seed <plugin> [--courses=<n>] [--lessons=<n>] [--quizzes=<n>] [--users=<n>] [--wait]
 *   wp tangible-populater status <process-id>
 *   wp tangible-populater logs   <process-id>
 *   wp tangible-populater cancel <process-id>
 */
class SeedCommand {

	/**
	 * Creates the command with its seeding or reset service.
	 *
	 * @param SeedingManager $manager Manager used to start, inspect and cancel seeding processes.
	 */
	public function __construct( private readonly SeedingManager $manager ) {}

	/**
	 * Starts a seeding process.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Plugin slug to seed (learndash | lifterlms | tangible-lms).
	 *
	 * [--courses=<n>]
	 * : Number of courses to create. Default: 5.
	 *
	 * [--lessons=<n>]
	 * : Lessons per course (used when --lessons-per-section is not set). Default: 10.
	 *
	 * [--lessons-per-section=<n>]
	 * : Lessons per section. When set with --sections, total lessons = sections × lessons-per-section.
	 *
	 * [--sections=<n>]
	 * : Sections per course. Default: 1.
	 *
	 * [--quizzes=<n>]
	 * : Quizzes per section/topic/module (depends on LMS). Default: 1.
	 *
	 * [--questions=<n>]
	 * : Questions per quiz. Default: 10.
	 *
	 * [--users=<n>]
	 * : Number of users to create. Default: 100.
	 *
	 * [--groups=<n>]
	 * : Number of groups to create. Default: 1.
	 *
	 * [--password=<password>]
	 * : Shared password for all seeded users. Default: StressTest#2026
	 *
	 * [--wait]
	 * : Wait for the process to complete, polling every second.
	 *
	 * ## EXAMPLES
	 *
	 *   wp tangible-populater seed learndash --courses=10 --wait
	 *
	 * @param array<int, string>   $args      Positional command arguments.
	 * @param array<string, mixed> $assocArgs Associative command arguments.
	 * @return void
	 */
	public function seed( array $args, array $assocArgs ): void {
		$plugin = $args[0] ?? '';

		$config = array(
			'plugin'              => $plugin,
			'courses'             => (int) ( $assocArgs['courses'] ?? SeedConfig::DEFAULT_COURSES ),
			'lessons_per_course'  => (int) ( $assocArgs['lessons'] ?? SeedConfig::DEFAULT_LESSONS_PER_COURSE ),
			'quizzes_per_section' => (int) ( $assocArgs['quizzes'] ?? SeedConfig::DEFAULT_QUIZZES_PER_SECTION ),
			'questions_per_quiz'  => (int) ( $assocArgs['questions'] ?? SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ ),
			'topics_per_lesson'   => (int) ( $assocArgs['topics'] ?? SeedConfig::DEFAULT_TOPICS_PER_LESSON ),
			'sections_per_course' => (int) ( $assocArgs['sections'] ?? SeedConfig::DEFAULT_SECTIONS_PER_COURSE ),
			'lessons_per_section' => (int) ( $assocArgs['lessons-per-section'] ?? 0 ),
			'modules_per_course'  => (int) ( $assocArgs['modules'] ?? SeedConfig::DEFAULT_MODULES_PER_COURSE ),
			'users'               => (int) ( $assocArgs['users'] ?? SeedConfig::DEFAULT_USERS ),
			'groups'              => (int) ( $assocArgs['groups'] ?? SeedConfig::DEFAULT_GROUPS ),
			'user_password'       => $assocArgs['password'] ?? null,
		);

		try {
			$processId = $this->manager->start( $config );
		} catch ( \Throwable $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}

		\WP_CLI::success( "Seeding started. Process ID: $processId" );

		$shouldWait = isset( $assocArgs['wait'] ) && false !== $assocArgs['wait'];

		if ( ! $shouldWait ) {
			\WP_CLI::line( 'Run `wp tangible-populater status ' . $processId . '` to check progress.' );
			return;
		}

		$this->pollUntilDone( $processId );
	}

	/**
	 * Displays the status of a seeding process.
	 *
	 * ## OPTIONS
	 *
	 * <process-id>
	 * : The process ID returned by the seed command.
	 *
	 * [--format=<format>]
	 * : Output format (table | json | csv). Default: table.
	 *
	 * @param array<int, string>   $args      Positional command arguments.
	 * @param array<string, mixed> $assocArgs Associative command arguments.
	 * @return void
	 */
	public function status( array $args, array $assocArgs ): void {
		$processId = $args[0] ?? '';
		$status    = $this->manager->getStatus( $processId );
		$format    = $assocArgs['format'] ?? 'table';

		\WP_CLI\Utils\format_items( $format, array( $status->toArray() ), array_keys( $status->toArray() ) );
	}

	/**
	 * Displays log entries for a seeding process.
	 *
	 * ## OPTIONS
	 *
	 * <process-id>
	 * : The process ID.
	 *
	 * [--format=<format>]
	 * : Output format (table | json | csv). Default: table.
	 *
	 * @param array<int, string>   $args      Positional command arguments.
	 * @param array<string, mixed> $assocArgs Associative command arguments.
	 * @return void
	 */
	public function logs( array $args, array $assocArgs ): void {
		$processId = $args[0] ?? '';
		$logs      = $this->manager->getLogs( $processId );
		$format    = $assocArgs['format'] ?? 'table';

		if ( empty( $logs ) ) {
			\WP_CLI::line( 'No log entries found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $logs, array( 'timestamp', 'level', 'message' ) );
	}

	/**
	 * Cancels a running seeding process.
	 *
	 * ## OPTIONS
	 *
	 * <process-id>
	 * : The process ID to cancel.
	 *
	 * @param array<int, string>   $args      Positional command arguments.
	 * @param array<string, mixed> $assocArgs Associative command arguments.
	 * @return void
	 */
	public function cancel( array $args, array $assocArgs ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by the WP-CLI command signature.
		$processId = $args[0] ?? '';

		if ( $this->manager->cancel( $processId ) ) {
			\WP_CLI::success( "Process $processId has been cancelled." );
		} else {
			\WP_CLI::error( "Could not cancel process $processId (already completed, failed, or not found)." );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Shows a progress bar until the seeding process stops and reports the final status.
	 *
	 * @param string $processId ID of the seeding process to follow.
	 * @return void
	 */
	private function pollUntilDone( string $processId ): void {
		$status   = $this->manager->getStatus( $processId );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Seeding…', max( 1, $status->getTotal() ) );

		while ( true ) {
			$status = $this->manager->getStatus( $processId );

			$progress->tick( $status->getProcessed() );

			if ( ! $status->isRunning() && $status->getStatus() !== SeedingStatus::STATUS_PENDING ) {
				break;
			}

			sleep( 1 );
		}

		$progress->finish();

		match ( $status->getStatus() ) {
			SeedingStatus::STATUS_COMPLETED => \WP_CLI::success( 'Seeding completed.' ),
			SeedingStatus::STATUS_CANCELLED => \WP_CLI::warning( 'Seeding was cancelled.' ),
			SeedingStatus::STATUS_FAILED    => \WP_CLI::error( 'Seeding failed: ' . ( $status->getError() ?? 'unknown error' ) ),
			default                         => \WP_CLI::line( 'Final status: ' . $status->getStatus() ),
		};
	}
}
