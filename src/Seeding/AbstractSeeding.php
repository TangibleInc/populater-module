<?php
/**
 * Abstract seeding.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\Logger;

/**
 * Abstract base for the seeding process.
 */
abstract class AbstractSeeding extends \WP_Background_Process {

	/**
	 * Background process prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'tangible_populater';

	/**
	 * Background process action name.
	 *
	 * @var string
	 */
	protected $action = 'seed';

	/**
	 * Classes permitted when unserializing batch data; false disallows all classes.
	 *
	 * @var bool|list<class-string>
	 */
	protected $allowed_batch_data_classes = false;

	/**
	 * Consecutive errors tolerated before the process is aborted.
	 */
	private const ERROR_THRESHOLD = 3;

	/**
	 * Repository that persists seeding process state.
	 *
	 * @var ProcessRepository
	 */
	protected readonly ProcessRepository $repository;

	/**
	 * Creates the seeding process for a seeder and initialises the background process.
	 *
	 * @param AbstractSeeder         $seeder     Seeder that builds the queue and performs the work.
	 * @param ProcessRepository|null $repository Repository used to persist process state, or null to create a default one.
	 */
	public function __construct(
		protected readonly AbstractSeeder $seeder,
		?ProcessRepository $repository = null,
	) {
		$this->repository = $repository ?? new ProcessRepository();
		parent::__construct( false );
	}

	/**
	 * Builds the seed queue, records a pending status and dispatches the background process.
	 *
	 * @param array<string, mixed> $config Raw seeding configuration; the plugin slug is forced to this seeder's slug.
	 * @return string UUID of the newly created seeding process.
	 */
	public function start( array $config ): string {
		$processId  = wp_generate_uuid4();
		$seedConfig = SeedConfig::fromArray( array_merge( $config, array( 'plugin' => $this->seeder->getSlug() ) ) );
		$queue      = $this->seeder->buildSeedQueue( $seedConfig );

		$this->saveStatusForProcess(
			$processId,
			array(
				'status'    => SeedingStatus::STATUS_PENDING,
				'plugin'    => $this->seeder->getSlug(),
				'total'     => count( $queue ),
				'processed' => 0,
				'errors'    => 0,
				'error'     => null,
				'timestamp' => time(),
			)
		);
		$this->repository->setActiveProcess( $processId );

		$chunkSize = 500;
		$i         = 0;
		foreach ( $queue as $item ) {
			$this->push_to_queue(
				array(
					'process_id' => $processId,
					'type'       => $item->type,
					'data'       => $item->data,
				)
			);
			++$i;
			if ( 0 === $i % $chunkSize ) {
				$this->save();
			}
		}

		$this->save()->dispatch();

		return $processId;
	}

	/**
	 * Marks a pending or running process as cancelled and discards its ID map.
	 *
	 * @param string $processId ID of the seeding process to cancel.
	 * @return bool True if the process was cancelled, false if it was not in a cancellable state.
	 */
	public function cancelProcess( string $processId ): bool {
		$statusData = $this->loadStatusForProcess( $processId );
		$status     = SeedingStatus::fromArray( $processId, $statusData );

		if ( ! $status->canBeCancelled() ) {
			return false;
		}

		// Per-process cancel: mark status only; do not cancel the entire LMS queue.
		$this->saveStatusForProcess(
			$processId,
			array_merge(
				$statusData,
				array(
					'status' => SeedingStatus::STATUS_CANCELLED,
				)
			)
		);
		$this->repository->deleteIdMap( $processId );
		$this->repository->clearActiveProcessIfMatches( $processId );

		return true;
	}

	/**
	 * Loads the current status of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return SeedingStatus Status value object for the process.
	 */
	public function getStatus( string $processId ): SeedingStatus {
		return SeedingStatus::fromArray( $processId, $this->loadStatusForProcess( $processId ) );
	}

	/**
	 * Retrieves the stored log entries of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return list<array{level: string, message: string, timestamp: int}> Log entries, oldest first.
	 */
	public function getLogs( string $processId ): array {
		return $this->repository->getLogs( $processId );
	}

	/**
	 * Processes one queued item and updates the process status, progress and error count.
	 *
	 * @param array{process_id: string, type: string, data: array<string, mixed>} $item Queue item pushed by start().
	 * @return false Always false so the item is removed from the queue.
	 */
	protected function task( $item ) {
		$processId = (string) ( $item['process_id'] ?? '' );

		if ( '' === $processId ) {
			return false;
		}

		$statusData = $this->loadStatusForProcess( $processId );
		$status     = $statusData['status'] ?? '';

		if ( SeedingStatus::STATUS_CANCELLED === $status ) {
			return false;
		}

		if ( SeedingStatus::STATUS_FAILED === $status ) {
			return false;
		}

		if ( SeedingStatus::STATUS_PENDING === $status ) {
			$this->saveStatusForProcess(
				$processId,
				array_merge(
					$statusData,
					array(
						'status' => SeedingStatus::STATUS_RUNNING,
					)
				)
			);
			$statusData = $this->loadStatusForProcess( $processId );
		}

		$logger    = new Logger( $processId );
		$queueItem = array(
			'type' => $item['type'],
			'data' => SeedingIdMap::enrich( $processId, $item['type'], $item['data'], $this->repository ),
		);

		$errorsBefore = $this->countLogErrors( $logger );

		try {
			$this->processItem( $queueItem, $processId, $logger );
		} catch ( \Throwable $e ) {
			$logger->error( sprintf( '[%s] %s', $queueItem['type'], $e->getMessage() ) );
		}

		$statusData = $this->loadStatusForProcess( $processId );

		if ( SeedingStatus::STATUS_CANCELLED === $statusData['status'] ) {
			return false;
		}

		$errors = (int) ( $statusData['errors'] ?? 0 ) + max( 0, $this->countLogErrors( $logger ) - $errorsBefore );

		$processed = ( $statusData['processed'] ?? 0 ) + 1;
		$total     = (int) ( $statusData['total'] ?? 0 );

		$statusData['processed'] = $processed;
		$statusData['errors']    = $errors;

		if ( $errors >= self::ERROR_THRESHOLD ) {
			$statusData['status'] = SeedingStatus::STATUS_FAILED;
			$statusData['error']  = $statusData['error'] ?? 'Too many errors during seeding.';
		} elseif ( $processed >= $total && $total > 0 ) {
			$statusData['status'] = SeedingStatus::STATUS_COMPLETED;
		} else {
			$statusData['status'] = SeedingStatus::STATUS_RUNNING;
		}

		$this->saveStatusForProcess( $processId, $statusData );

		if ( SeedingStatus::STATUS_COMPLETED === $statusData['status'] ) {
			$this->repository->deleteIdMap( $processId );
			$logger->info( 'Seeding completed.' );
		}

		if ( in_array(
			$statusData['status'],
			array(
				SeedingStatus::STATUS_COMPLETED,
				SeedingStatus::STATUS_FAILED,
			),
			true
		) ) {
			$this->repository->clearActiveProcessIfMatches( $processId );
		}

		return false;
	}

	/**
	 * Executes the seeding work for a single queue item.
	 *
	 * @param array{type: string, data: array<string, mixed>} $item      Queue item type and enriched payload.
	 * @param string                                          $processId ID of the seeding process the item belongs to.
	 * @param Logger                                          $logger    Logger that records progress and errors for the process.
	 * @return void
	 */
	abstract protected function processItem( array $item, string $processId, Logger $logger ): void;

	/**
	 * Persists the status data of a seeding process.
	 *
	 * @param string               $processId ID of the seeding process.
	 * @param array<string, mixed> $data      Status data to store.
	 * @return void
	 */
	protected function saveStatusForProcess( string $processId, array $data ): void {
		$this->repository->saveStatus( $processId, $data );
	}

	/**
	 * Loads the raw status data of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return array<string, mixed> Stored status data, or an empty array if none exists.
	 */
	protected function loadStatusForProcess( string $processId ): array {
		return $this->repository->getStatus( $processId );
	}

	/**
	 * Counts the entries logged at error level.
	 *
	 * @param Logger $logger Logger whose entries are inspected.
	 * @return int Number of error-level entries.
	 */
	private function countLogErrors( Logger $logger ): int {
		return count(
			array_filter(
				$logger->getEntries(),
				static fn( array $entry ) => ( $entry['level'] ?? '' ) === 'error',
			)
		);
	}
}
