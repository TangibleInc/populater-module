<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Abstraction over background seeding dispatch and lifecycle.
 */
interface SeedingJobRunner
{
    /**
     * @param array<string, mixed> $config
     */
    public function start(array $config): string;

    public function cancel(string $processId): bool;

    public function getStatus(string $processId): SeedingStatus;

    /**
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array;

    public function isRunning(): bool;
}
