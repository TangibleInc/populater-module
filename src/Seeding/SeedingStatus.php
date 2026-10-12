<?php
/**
 * Seeding status.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Immutable value object representing the state of a seeding process.
 */
final class SeedingStatus {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_FAILED    = 'failed';

	/**
	 * Creates an immutable status.
	 *
	 * @param string      $id        Seeding process ID.
	 * @param string      $status    One of the STATUS_* constants.
	 * @param int         $total     Total number of queue items.
	 * @param int         $processed Number of queue items processed so far.
	 * @param string|null $error     Error message when the process failed, otherwise null.
	 * @param string|null $plugin    Slug of the LMS plugin being seeded, if known.
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $status = self::STATUS_PENDING,
		private readonly int $total = 0,
		private readonly int $processed = 0,
		private readonly ?string $error = null,
		private readonly ?string $plugin = null,
	) {}

	// -------------------------------------------------------------------------
	// Accessors
	// -------------------------------------------------------------------------

	/**
	 * Gets the seeding process ID.
	 *
	 * @return string Process ID.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Gets the status string.
	 *
	 * @return string One of the STATUS_* constants.
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * Gets the total number of queue items.
	 *
	 * @return int Total items.
	 */
	public function getTotal(): int {
		return $this->total;
	}

	/**
	 * Gets the number of queue items processed so far.
	 *
	 * @return int Processed items.
	 */
	public function getProcessed(): int {
		return $this->processed;
	}

	/**
	 * Gets the failure message.
	 *
	 * @return string|null Error message, or null if there is none.
	 */
	public function getError(): ?string {
		return $this->error;
	}

	/**
	 * Gets the slug of the LMS plugin being seeded.
	 *
	 * @return string|null Plugin slug, or null if unknown.
	 */
	public function getPlugin(): ?string {
		return $this->plugin;
	}

	/**
	 * Calculates completion as a percentage rounded to two decimals.
	 *
	 * @return float Percentage from 0 to 100, or 0 when there are no items.
	 */
	public function getProgress(): float {
		if ( 0 === $this->total ) {
			return 0.0;
		}
		return round( ( $this->processed / $this->total ) * 100, 2 );
	}

	// -------------------------------------------------------------------------
	// State helpers
	// -------------------------------------------------------------------------

	/**
	 * Checks whether the process is running.
	 *
	 * @return bool True if the status is running.
	 */
	public function isRunning(): bool {
		return self::STATUS_RUNNING === $this->status;
	}

	/**
	 * Checks whether the process has completed.
	 *
	 * @return bool True if the status is completed.
	 */
	public function isCompleted(): bool {
		return self::STATUS_COMPLETED === $this->status;
	}

	/**
	 * Checks whether the process was cancelled.
	 *
	 * @return bool True if the status is cancelled.
	 */
	public function isCancelled(): bool {
		return self::STATUS_CANCELLED === $this->status;
	}

	/**
	 * Checks whether the process failed.
	 *
	 * @return bool True if the status is failed.
	 */
	public function isFailed(): bool {
		return self::STATUS_FAILED === $this->status;
	}

	/**
	 * Checks whether the process is still pending or running and so can be cancelled.
	 *
	 * @return bool True if the process can be cancelled.
	 */
	public function canBeCancelled(): bool {
		return in_array( $this->status, array( self::STATUS_PENDING, self::STATUS_RUNNING ), true );
	}

	// -------------------------------------------------------------------------
	// Serialisation
	// -------------------------------------------------------------------------

	/**
	 * Converts the status to an array, omitting null values and including the computed progress.
	 *
	 * @return array<string, mixed> Status data keyed by field name.
	 */
	public function toArray(): array {
		return array_filter(
			array(
				'id'        => $this->id,
				'status'    => $this->status,
				'total'     => $this->total,
				'processed' => $this->processed,
				'progress'  => $this->getProgress(),
				'error'     => $this->error,
				'plugin'    => $this->plugin,
			),
			static fn( mixed $value ) => null !== $value
		);
	}

	/**
	 * Builds a status from stored status data, defaulting missing values.
	 *
	 * @param string               $id   Seeding process ID.
	 * @param array<string, mixed> $data Stored status data.
	 * @return self Status value object.
	 */
	public static function fromArray( string $id, array $data ): self {
		return new self(
			id:        $id,
			status:    $data['status'] ?? self::STATUS_PENDING,
			total:     (int) ( $data['total'] ?? 0 ),
			processed: (int) ( $data['processed'] ?? 0 ),
			error:     isset( $data['error'] ) ? (string) $data['error'] : null,
			plugin:    isset( $data['plugin'] ) ? (string) $data['plugin'] : null,
		);
	}
}
