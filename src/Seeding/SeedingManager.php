<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\PluginDetector;
use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Orchestrates seeding across multiple supported LMS plugins.
 */
class SeedingManager
{
    /** @var array<string, AbstractSeeder> */
    private array $seeders;

    /** @var array<string, SeedingJobRunner> */
    private array $runners;

    private readonly ProcessRepository $repository;

    public function __construct(
        private readonly PluginDetector $detector,
        private readonly LmsPluginRegistry $registry = new LmsPluginRegistry(),
        ?ProcessRepository $repository = null,
    ) {
        $this->repository = $repository ?? new ProcessRepository();
        $this->seeders    = [];

        foreach ($this->registry->all() as $slug => $plugin) {
            $this->seeders[$slug] = $plugin->createSeeder();
        }
    }

    /**
     * Constructs LMS seeding processes so WP_Background_Process ajax/cron hooks
     * are registered for the lifetime of the request.
     */
    public function registerBackgroundProcesses(): void
    {
        $this->runners = [];

        foreach ($this->registry->all() as $slug => $plugin) {
            $seeder                 = $this->seeders[$slug];
            $process                = $plugin->createProcess($seeder, $this->repository);
            $this->runners[$slug]   = new WpBackgroundSeedingRunner($process);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function start(array $config): string
    {
        $seedConfig = SeedConfig::fromArray($config);
        $seeder     = $this->resolveSeeder($seedConfig->plugin);

        return $this->getRunner($seeder->getSlug())->start($seedConfig->toArray());
    }

    public function cancel(string $processId): bool
    {
        $slug = $this->resolvePluginSlugForProcess($processId);

        if ($slug === null) {
            return false;
        }

        return $this->getRunner($slug)->cancel($processId);
    }

    public function getStatus(string $processId): SeedingStatus
    {
        $data = $this->repository->getStatus($processId);

        return SeedingStatus::fromArray($processId, $data);
    }

    public function getActiveProcess(): ?SeedingStatus
    {
        $processId = $this->repository->findActiveProcessId();

        if ($processId === null) {
            return null;
        }

        $status = $this->getStatus($processId);

        if (!$status->canBeCancelled()) {
            $this->repository->clearActiveProcess();

            return null;
        }

        return $status;
    }

    /**
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return $this->repository->getLogs($processId);
    }

    /**
     * @return list<array{slug: string, name: string, active: bool}>
     */
    public function getSupportedPlugins(): array
    {
        return array_values(array_map(
            fn(AbstractSeeder $seeder) => [
                'slug'   => $seeder->getSlug(),
                'name'   => $seeder->getName(),
                'active' => $seeder->isActive(),
            ],
            $this->seeders
        ));
    }

    private function resolveSeeder(string $slug): AbstractSeeder
    {
        if (!isset($this->seeders[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported plugin "%s". Available: %s', $slug, implode(', ', array_keys($this->seeders)))
            );
        }

        $seeder = $this->seeders[$slug];

        if (!$seeder->isActive()) {
            throw new \RuntimeException(
                sprintf('Plugin "%s" is not active. Activate it before seeding.', $seeder->getName())
            );
        }

        return $seeder;
    }

    private function getRunner(string $slug): SeedingJobRunner
    {
        $this->ensureRunnersRegistered();

        if (!isset($this->runners[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('No seeding process for plugin "%s".', $slug)
            );
        }

        return $this->runners[$slug];
    }

    private function ensureRunnersRegistered(): void
    {
        if (!isset($this->runners)) {
            $this->registerBackgroundProcesses();
        }
    }

    private function resolvePluginSlugForProcess(string $processId): ?string
    {
        $data = $this->repository->getStatus($processId);
        $slug = $data['plugin'] ?? null;

        return is_string($slug) && $this->registry->has($slug) ? $slug : null;
    }
}
