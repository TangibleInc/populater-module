<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\PluginDetector;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeders\LearnDashSeeder;
use Tangible\Populater\Seeders\LifterLMSSeeder;
use Tangible\Populater\Seeders\TangibleLMSSeeder;

/**
 * Orchestrates seeding across multiple supported LMS plugins.
 *
 * Uses composition rather than inheritance: it instantiates a SeedingProcess
 * (concrete AbstractSeeding) with the correct seeder per request.
 *
 * For status / cancel / logs operations the seeder is irrelevant because those
 * only read and write WP options keyed by process ID.
 */
class SeedingManager
{
    /** @var array<string, AbstractSeeder> */
    private array $seeders;

    public function __construct(private readonly PluginDetector $detector)
    {
        $this->seeders = [
            'learndash'    => new LearnDashSeeder(),
            'lifterlms'    => new LifterLMSSeeder(),
            'tangible-lms' => new TangibleLMSSeeder(),
        ];
    }

    /**
     * Starts a seeding process for the given plugin.
     *
     * @param  array<string, mixed> $config  Keys: plugin (slug), courses, lessons_per_course, quizzes_per_lesson, users.
     * @return string  Process ID.
     * @throws \InvalidArgumentException When the plugin slug is not supported.
     * @throws \RuntimeException         When the plugin is not active.
     */
    public function start(array $config): string
    {
        $slug   = (string) ($config['plugin'] ?? '');
        $seeder = $this->resolveSeeder($slug);

        return (new SeedingProcess($seeder))->start($config);
    }

    /**
     * Attempts to cancel a running or pending process.
     */
    public function cancel(string $processId): bool
    {
        return $this->makeAnyProcess()->cancel($processId);
    }

    /**
     * Returns the current status of a process.
     */
    public function getStatus(string $processId): SeedingStatus
    {
        return $this->makeAnyProcess()->getStatus($processId);
    }

    /**
     * Returns the log entries for a process.
     *
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return $this->makeAnyProcess()->getLogs($processId);
    }

    /**
     * Forwards a cron batch-processing call to the correct process.
     * Registered as the handler for the tangible_populater_process_batch hook.
     */
    public function processBatch(string $processId): void
    {
        $this->makeAnyProcess()->processBatch($processId);
    }

    /**
     * Returns metadata about all supported plugins and whether they are active.
     *
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    /**
     * Creates a SeedingProcess with any seeder for operations that do not
     * actually use the seeder (cancel, getStatus, getLogs).
     */
    private function makeAnyProcess(): SeedingProcess
    {
        return new SeedingProcess(reset($this->seeders));
    }
}
