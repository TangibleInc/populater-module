<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\LMS\LearnDash\LearnDashSeedingProcess;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeedingProcess;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeedingProcess;
use Tangible\Populater\PluginDetector;
use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeeder;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeeder;
use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Orchestrates seeding across multiple supported LMS plugins.
 *
 * Instantiates the LMS-specific AbstractSeeding subclass with the correct
 * seeder per request. Background-process hooks are registered when each
 * process is constructed during registerBackgroundProcesses().
 */
class SeedingManager
{
    /** @var array<string, AbstractSeeder> */
    private array $seeders;

    /** @var array<string, AbstractSeeding> */
    private array $processes;

    public function __construct(private readonly PluginDetector $detector)
    {
        $this->seeders = [
            'learndash'    => new LearnDashSeeder(),
            'lifterlms'    => new LifterLMSSeeder(),
            'tangible-lms' => new TangibleLMSSeeder(),
        ];
    }

    /**
     * Constructs LMS seeding processes so WP_Background_Process ajax/cron hooks
     * are registered for the lifetime of the request.
     */
    public function registerBackgroundProcesses(): void
    {
        $this->processes = [
            'learndash'    => new LearnDashSeedingProcess($this->seeders['learndash']),
            'lifterlms'    => new LifterLMSSeedingProcess($this->seeders['lifterlms']),
            'tangible-lms' => new TangibleLMSSeedingProcess($this->seeders['tangible-lms']),
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

        return $this->getProcess($seeder->getSlug())->start($config);
    }

    /**
     * Attempts to cancel a running or pending process.
     */
    public function cancel(string $processId): bool
    {
        $slug = $this->resolvePluginSlugForProcess($processId);

        if ($slug === null) {
            return false;
        }

        return $this->getProcess($slug)->cancelProcess($processId);
    }

    /**
     * Returns the current status of a process.
     */
    public function getStatus(string $processId): SeedingStatus
    {
        return $this->getAnyProcess()->getStatus($processId);
    }

    /**
     * Returns the log entries for a process.
     *
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return $this->getAnyProcess()->getLogs($processId);
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

    private function getProcess(string $slug): AbstractSeeding
    {
        $this->ensureProcessesRegistered();

        if (!isset($this->processes[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('No seeding process for plugin "%s".', $slug)
            );
        }

        return $this->processes[$slug];
    }

    private function getAnyProcess(): AbstractSeeding
    {
        $this->ensureProcessesRegistered();

        return reset($this->processes);
    }

    private function ensureProcessesRegistered(): void
    {
        if (!isset($this->processes)) {
            $this->registerBackgroundProcesses();
        }
    }

    private function resolvePluginSlugForProcess(string $processId): ?string
    {
        $this->ensureProcessesRegistered();

        $data = get_option('tangible_populater_status_' . $processId, null);

        if (!is_array($data)) {
            return null;
        }

        $slug = $data['plugin'] ?? null;

        return is_string($slug) && isset($this->processes[$slug]) ? $slug : null;
    }
}
