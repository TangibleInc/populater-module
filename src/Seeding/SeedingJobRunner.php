<?php
/**
 * Seeding job runner.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Abstraction over background seeding dispatch and lifecycle.
 */
interface SeedingJobRunner {

	/**
	 * Starts a background seeding process.
	 *
	 * @param array<string, mixed> $config Seeding configuration.
	 * @return string ID of the newly started process.
	 */
	public function start( array $config ): string;

	/**
	 * Cancels a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return bool True if the process was cancelled, false if it could not be.
	 */
	public function cancel( string $processId ): bool;

	/**
	 * Gets the status of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return SeedingStatus Status value object for the process.
	 */
	public function getStatus( string $processId ): SeedingStatus;

	/**
	 * Gets the log entries of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return list<array{level: string, message: string, timestamp: int}> Log entries, oldest first.
	 */
	public function getLogs( string $processId ): array;

	/**
	 * Checks whether the background process still has queued work.
	 *
	 * @return bool True if the process is queued or running.
	 */
	public function isRunning(): bool;
}
