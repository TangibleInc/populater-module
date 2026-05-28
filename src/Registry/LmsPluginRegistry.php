<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;

/**
 * Resolves registered LMS plugins from the {@see AbstractLmsPlugin::FILTER} hook.
 */
class LmsPluginRegistry
{
    /**
     * @return array<string, AbstractLmsPlugin>
     */
    public function all(): array
    {
        /** @var array<string, AbstractLmsPlugin> $plugins */
        $plugins = apply_filters(AbstractLmsPlugin::FILTER, []);

        if ($plugins !== []) {
            return $plugins;
        }

        return AbstractLmsPlugin::getRegistered();
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->all());
    }

    public function get(string $slug): AbstractLmsPlugin
    {
        $plugins = $this->all();

        if (!isset($plugins[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown plugin slug "%s". Supported: %s', $slug, implode(', ', array_keys($plugins)))
            );
        }

        return $plugins[$slug];
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

        foreach ($this->all() as $slug => $plugin) {
            $plugins[$slug] = [
                'name' => $plugin->getName(),
                'file' => $plugin->getPluginFile(),
            ];
        }

        return $plugins;
    }

    public function isPluginActive(string $slug): bool
    {
        return $this->get($slug)->isActive();
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
        return $this->get($slug)->createSeeder();
    }

    public function createProcess(string $slug, AbstractSeeder $seeder): AbstractSeeding
    {
        return $this->get($slug)->createProcess($seeder);
    }
}
