<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * One item in the seeding queue.
 */
final class SeedQueueItem
{
    public function __construct(
        public readonly string $type,
        /** @var array<string, mixed> */
        public readonly array $data,
    ) {}

    /**
     * @param array{type: string, data: array<string, mixed>} $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            type: (string) ($item['type'] ?? ''),
            data: is_array($item['data'] ?? null) ? $item['data'] : [],
        );
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
