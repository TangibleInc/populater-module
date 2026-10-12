<?php
/**
 * Logger.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

use Tangible\Populater\Seeding\ProcessRepository;

/**
 * Persists log entries for a seeding process in a WP option.
 */
class Logger {

	private const MAX_ENTRIES = 500;

	/**
	 * Buffered log entries.
	 *
	 * @var list<array{level: string, message: string, timestamp: int}>
	 */
	private array $entries = array();

	/**
	 * Loads any stored log entries for the process.
	 *
	 * @param string $processId ID of the seeding process the log belongs to.
	 */
	public function __construct(
		private readonly string $processId,
	) {
		$stored        = get_option( ProcessRepository::PREFIX_LOGS . $processId, array() );
		$this->entries = is_array( $stored ) ? self::normalizeEntries( $stored ) : array();
	}

	/**
	 * Keeps only well-formed entries from a stored option value.
	 *
	 * @param array<mixed> $stored Raw option value.
	 * @return list<array{level: string, message: string, timestamp: int}> Valid entries, reindexed.
	 */
	private static function normalizeEntries( array $stored ): array {
		$entries = array();

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['level'], $entry['message'], $entry['timestamp'] ) ) {
				continue;
			}

			if ( ! is_string( $entry['level'] ) || ! is_string( $entry['message'] ) || ! is_numeric( $entry['timestamp'] ) ) {
				continue;
			}

			$entries[] = array(
				'level'     => $entry['level'],
				'message'   => $entry['message'],
				'timestamp' => (int) $entry['timestamp'],
			);
		}

		return $entries;
	}

	/**
	 * Gets the ID of the process this logger belongs to.
	 *
	 * @return string Process ID.
	 */
	public function getProcessId(): string {
		return $this->processId;
	}

	/**
	 * Appends an entry, trims the log to the most recent entries and saves it.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level, such as info, warning or error.
	 * @return void
	 */
	public function log( string $message, string $level = 'info' ): void {
		$this->entries[] = array(
			'level'     => $level,
			'message'   => $message,
			'timestamp' => time(),
		);

		if ( count( $this->entries ) > self::MAX_ENTRIES ) {
			$this->entries = array_slice( $this->entries, -self::MAX_ENTRIES );
		}

		update_option( ProcessRepository::PREFIX_LOGS . $this->processId, $this->entries, false );
	}

	/**
	 * Logs a message at info level.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function info( string $message ): void {
		$this->log( $message, 'info' );
	}

	/**
	 * Logs a message at warning level.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->log( $message, 'warning' );
	}

	/**
	 * Logs a message at error level.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function error( string $message ): void {
		$this->log( $message, 'error' );
	}

	/**
	 * Gets the buffered log entries.
	 *
	 * @return list<array{level: string, message: string, timestamp: int}> Entries, oldest first.
	 */
	public function getEntries(): array {
		return $this->entries;
	}

	/**
	 * Discards the buffered entries and deletes the stored option.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->entries = array();
		delete_option( ProcessRepository::PREFIX_LOGS . $this->processId );
	}
}
