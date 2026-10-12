<?php
/**
 * WP background seeding runner.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Wraps an LMS-specific {@see AbstractSeeding} background process.
 */
class WpBackgroundSeedingRunner implements SeedingJobRunner {

	/**
	 * Creates the runner around a background process.
	 *
	 * @param AbstractSeeding $process Background process to delegate to.
	 */
	public function __construct(
		private readonly AbstractSeeding $process,
	) {}

	/**
	 * Starts the wrapped background process.
	 *
	 * @param array<string, mixed> $config Seeding configuration.
	 * @return string ID of the newly started process.
	 */
	public function start( array $config ): string {
		return $this->process->start( $config );
	}

	/**
	 * Cancels a seeding process through the wrapped process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return bool True if the process was cancelled, false if it could not be.
	 */
	public function cancel( string $processId ): bool {
		return $this->process->cancelProcess( $processId );
	}

	/**
	 * Gets the status of a seeding process from the wrapped process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return SeedingStatus Status value object for the process.
	 */
	public function getStatus( string $processId ): SeedingStatus {
		return $this->process->getStatus( $processId );
	}

	/**
	 * Gets the log entries of a seeding process from the wrapped process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return list<array{level: string, message: string, timestamp: int}> Log entries, oldest first.
	 */
	public function getLogs( string $processId ): array {
		return $this->process->getLogs( $processId );
	}

	/**
	 * Checks whether the wrapped background process has queued work.
	 *
	 * @return bool True if the process is queued.
	 */
	public function isRunning(): bool {
		return $this->process->is_queued();
	}
}
