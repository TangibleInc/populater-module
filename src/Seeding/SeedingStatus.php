<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Immutable value object representing the state of a seeding process.
 */
final class SeedingStatus
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED    = 'failed';

    public function __construct(
        private readonly string  $id,
        private readonly string  $status    = self::STATUS_PENDING,
        private readonly int     $total     = 0,
        private readonly int     $processed = 0,
        private readonly ?string $error     = null,
    ) {}

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getProgress(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }
        return round(($this->processed / $this->total) * 100, 2);
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'status'    => $this->status,
            'total'     => $this->total,
            'processed' => $this->processed,
            'progress'  => $this->getProgress(),
            'error'     => $this->error,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $id, array $data): self
    {
        return new self(
            id:        $id,
            status:    $data['status']    ?? self::STATUS_PENDING,
            total:     (int) ($data['total']     ?? 0),
            processed: (int) ($data['processed'] ?? 0),
            error:     $data['error']     ?? null,
        );
    }
}
