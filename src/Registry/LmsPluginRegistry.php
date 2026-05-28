<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\LMS\LearnDash\LearnDashSeeder;
use Tangible\Populater\LMS\LearnDash\LearnDashSeedingProcess;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeeder;
use Tangible\Populater\LMS\LifterLMS\LifterLMSSeedingProcess;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeeder;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSSeedingProcess;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;

/**
 * Single source of truth for supported LMS plugins.
 *
 * Adding a fourth LMS requires one entry here plus seeder/process classes.
 */
class LmsPluginRegistry
{
    /** @var array<string, LmsPluginDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, LmsPluginDefinition>
     */
    public function all(): array
    {
        if ($this->definitions === null) {
            $this->definitions = $this->buildDefinitions();
        }

        return $this->definitions;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->all());
    }

    public function get(string $slug): LmsPluginDefinition
    {
        $definitions = $this->all();

        if (!isset($definitions[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown plugin slug "%s". Supported: %s', $slug, implode(', ', array_keys($definitions)))
            );
        }

        return $definitions[$slug];
    }

    public function has(string $slug): bool
    {
        return isset($this->all()[$slug]);
    }

    /**
     * @return array<string, array{name: string, file: string}>
     */
    public function getSupportedPluginsMetadata(): array
    {
        $plugins = [];

        foreach ($this->all() as $slug => $definition) {
            $plugins[$slug] = [
                'name' => $definition->name,
                'file' => $definition->pluginFile,
            ];
        }

        return $plugins;
    }

    public function isPluginActive(string $slug): bool
    {
        return is_plugin_active($this->get($slug)->pluginFile);
    }

    public function hasAnyActivePlugin(): bool
    {
        foreach ($this->slugs() as $slug) {
            if ($this->isPluginActive($slug)) {
                return true;
            }
        }

        return false;
    }

    public function createSeeder(string $slug): AbstractSeeder
    {
        $definition = $this->get($slug);
        $class      = $definition->seederClass;

        return new $class($definition);
    }

    public function createProcess(string $slug, AbstractSeeder $seeder): AbstractSeeding
    {
        $definition = $this->get($slug);
        $class      = $definition->processClass;

        return new $class($seeder);
    }

    /**
     * @return array<string, LmsPluginDefinition>
     */
    private function buildDefinitions(): array
    {
        return [
            'learndash' => new LmsPluginDefinition(
                slug: 'learndash',
                name: 'LearnDash LMS',
                pluginFile: 'sfwd-lms/sfwd_lms.php',
                seederClass: LearnDashSeeder::class,
                processClass: LearnDashSeedingProcess::class,
                backgroundAction: 'seed_learndash',
            ),
            'lifterlms' => new LmsPluginDefinition(
                slug: 'lifterlms',
                name: 'LifterLMS',
                pluginFile: 'lifterlms/lifterlms.php',
                seederClass: LifterLMSSeeder::class,
                processClass: LifterLMSSeedingProcess::class,
                backgroundAction: 'seed_lifterlms',
            ),
            'tangible-lms' => new LmsPluginDefinition(
                slug: 'tangible-lms',
                name: 'Tangible LMS',
                pluginFile: 'tangible-lms/tangible-lms.php',
                seederClass: TangibleLMSSeeder::class,
                processClass: TangibleLMSSeedingProcess::class,
                backgroundAction: 'seed_tangible_lms',
            ),
        ];
    }
}
