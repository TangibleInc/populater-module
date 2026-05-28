<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Wraps an LMS-specific {@see AbstractSeeding} background process.
 */
class WpBackgroundSeedingRunner implements SeedingJobRunner
{
    public function __construct(
        private readonly AbstractSeeding $process,
    ) {}

    public function start(array $config): string
    {
        return $this->process->start($config);
    }

    public function cancel(string $processId): bool
    {
        return $this->process->cancelProcess($processId);
    }

    public function getStatus(string $processId): SeedingStatus
    {
        return $this->process->getStatus($processId);
    }

    public function getLogs(string $processId): array
    {
        return $this->process->getLogs($processId);
    }

    public function isRunning(): bool
    {
        return $this->process->is_queue_empty() === false;
    }
}
