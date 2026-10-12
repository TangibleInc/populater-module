<?php
/**
 * Seed queue item.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * One item in the seeding queue.
 */
final class SeedQueueItem {

	/**
	 * Creates a queue item.
	 *
	 * @param string               $type Queue item type, such as course, lesson or quiz.
	 * @param array<string, mixed> $data Queue item payload.
	 */
	public function __construct(
		public readonly string $type,
		/**
		 * Queue item payload.
		 *
		 * @var array<string, mixed>
		 */
		public readonly array $data,
	) {}

	/**
	 * Builds a queue item from a stored array, defaulting missing values.
	 *
	 * @param array<string, mixed> $item Array with optional "type" and "data" keys.
	 * @return self Queue item.
	 */
	public static function fromArray( array $item ): self {
		return new self(
			type: (string) ( $item['type'] ?? '' ),
			data: is_array( $item['data'] ?? null ) ? $item['data'] : array(),
		);
	}

	/**
	 * Converts the queue item to its array form.
	 *
	 * @return array{type: string, data: array<string, mixed>} Item type and payload.
	 */
	public function toArray(): array {
		return array(
			'type' => $this->type,
			'data' => $this->data,
		);
	}
}
