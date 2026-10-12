<?php
/**
 * Process repository.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Support\Logger;

/**
 * Centralizes option keys for seeding process state.
 */
class ProcessRepository {

	public const PREFIX_STATUS  = 'tangible_populater_status_';
	public const PREFIX_LOGS    = 'tangible_populater_logs_';
	public const PREFIX_IDS     = 'tangible_populater_ids_';
	public const ACTIVE_PROCESS = 'tangible_populater_active_process';

	/**
	 * Reads the stored status data of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return array<string, mixed> Stored status data, or an empty array if none exists.
	 */
	public function getStatus( string $processId ): array {
		$data = get_option( self::PREFIX_STATUS . $processId, null );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Stores the status data of a seeding process.
	 *
	 * @param string               $processId ID of the seeding process.
	 * @param array<string, mixed> $data      Status data to store.
	 * @return void
	 */
	public function saveStatus( string $processId, array $data ): void {
		update_option( self::PREFIX_STATUS . $processId, $data, false );
	}

	/**
	 * Reads the log entries of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return list<array{level: string, message: string, timestamp: int}> Log entries, oldest first.
	 */
	public function getLogs( string $processId ): array {
		return ( new Logger( $processId ) )->getEntries();
	}

	/**
	 * Appends a log entry to a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @param string $message   Log message.
	 * @param string $level     Log level, such as info, warning or error.
	 * @return void
	 */
	public function appendLog( string $processId, string $message, string $level = 'info' ): void {
		( new Logger( $processId ) )->log( $message, $level );
	}

	/**
	 * Reads the map of created post IDs for a seeding process, always including the courses and lessons keys.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return array<string, mixed> ID map keyed by entity name, each holding nested created IDs keyed by index.
	 */
	public function getIdMap( string $processId ): array {
		$stored = get_option( self::PREFIX_IDS . $processId, null );

		if ( ! is_array( $stored ) ) {
			return array(
				'courses' => array(),
				'lessons' => array(),
			);
		}

		$map = array(
			'courses' => is_array( $stored['courses'] ?? null ) ? $stored['courses'] : array(),
			'lessons' => is_array( $stored['lessons'] ?? null ) ? $stored['lessons'] : array(),
		);

		foreach ( array( 'topics', 'sections', 'modules', 'section_lessons', 'section_quiz_topics', 'lesson_quiz_topics', 'groups', 'group_admins' ) as $entity ) {
			if ( isset( $stored[ $entity ] ) && is_array( $stored[ $entity ] ) ) {
				$map[ $entity ] = $stored[ $entity ];
			}
		}

		return $map;
	}

	/**
	 * Stores the map of created post IDs for a seeding process.
	 *
	 * @param string               $processId ID of the seeding process.
	 * @param array<string, mixed> $map       ID map keyed by entity name.
	 * @return void
	 */
	public function saveIdMap( string $processId, array $map ): void {
		update_option( self::PREFIX_IDS . $processId, $map, false );
	}

	/**
	 * Deletes the stored ID map of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return void
	 */
	public function deleteIdMap( string $processId ): void {
		delete_option( self::PREFIX_IDS . $processId );
	}

	/**
	 * Deletes the status, ID map and logs of a seeding process and clears it as the active process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return void
	 */
	public function deleteProcess( string $processId ): void {
		delete_option( self::PREFIX_STATUS . $processId );
		delete_option( self::PREFIX_IDS . $processId );
		( new Logger( $processId ) )->clear();
		$this->clearActiveProcessIfMatches( $processId );
	}

	/**
	 * Records a seeding process as the active one.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return void
	 */
	public function setActiveProcess( string $processId ): void {
		update_option( self::ACTIVE_PROCESS, $processId, false );
	}

	/**
	 * Reads the active process ID stored in the option without verifying its status.
	 *
	 * @return string|null Stored process ID, or null if none is stored.
	 */
	public function getStoredActiveProcessId(): ?string {
		$processId = get_option( self::ACTIVE_PROCESS, null );

		return is_string( $processId ) && '' !== $processId ? $processId : null;
	}

	/**
	 * Finds the active process, falling back to scanning stored statuses for the most recent pending or running one.
	 *
	 * @return string|null ID of the active process, or null if none is active.
	 */
	public function findActiveProcessId(): ?string {
		$stored = $this->getStoredActiveProcessId();

		if ( null !== $stored && $this->isActiveStatus( $this->getStatus( $stored ) ) ) {
			return $stored;
		}

		global $wpdb;

		$like = $wpdb->esc_like( self::PREFIX_STATUS ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			),
			ARRAY_A
		);

		$activeId        = null;
		$latestTimestamp = -1;
		$latestProgress  = -1;

		foreach ( $rows as $row ) {
			$data = maybe_unserialize( $row['option_value'] );

			if ( ! is_array( $data ) || ! $this->isActiveStatus( $data ) ) {
				continue;
			}

			$processId = substr( (string) $row['option_name'], strlen( self::PREFIX_STATUS ) );
			$timestamp = (int) ( $data['timestamp'] ?? 0 );
			$progress  = (int) ( $data['processed'] ?? 0 );

			if ( $timestamp > 0 || $latestTimestamp > 0 ) {
				if ( $timestamp >= $latestTimestamp ) {
					$latestTimestamp = $timestamp;
					$activeId        = $processId;
				}
			} elseif ( $progress >= $latestProgress ) {
					$latestProgress = $progress;
					$activeId       = $processId;
			}
		}//end foreach

		if ( null !== $activeId ) {
			$this->setActiveProcess( $activeId );

			return $activeId;
		}

		if ( null !== $stored ) {
			$this->clearActiveProcess();
		}

		return null;
	}

	/**
	 * Checks whether status data describes a pending or running process.
	 *
	 * @param array<string, mixed> $data Status data.
	 * @return bool True if the process is pending or running.
	 */
	private function isActiveStatus( array $data ): bool {
		return in_array(
			$data['status'] ?? '',
			array(
				SeedingStatus::STATUS_PENDING,
				SeedingStatus::STATUS_RUNNING,
			),
			true
		);
	}

	/**
	 * Clears the stored active process ID.
	 *
	 * @return void
	 */
	public function clearActiveProcess(): void {
		delete_option( self::ACTIVE_PROCESS );
	}

	/**
	 * Clears the stored active process ID only if it equals the given process ID.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return void
	 */
	public function clearActiveProcessIfMatches( string $processId ): void {
		if ( $this->getStoredActiveProcessId() === $processId ) {
			$this->clearActiveProcess();
		}
	}
}
