<?php

declare(strict_types=1);

namespace Tangible\Populater;

use Tangible\Populater\Registry\LmsPluginRegistry;

/**
 * Detects which supported LMS plugins are active.
 */
class PluginDetector
{
    public function __construct(
        private readonly LmsPluginRegistry $registry = new LmsPluginRegistry(),
    ) {}

    /**
     * Returns all supported plugin slugs and their metadata.
     *
     * @return array<string, array{name: string, file: string}>
     */
    public function getSupportedPlugins(): array
    {
        return $this->registry->getSupportedPluginsMetadata();
    }

    /**
     * Returns only the active supported plugins.
     *
     * @return array<string, array{name: string, file: string}>
     */
    public function getActivePlugins(): array
    {
        return array_filter(
            $this->getSupportedPlugins(),
            fn(string $slug) => $this->isPluginActive($slug),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Checks whether a supported plugin is active.
     *
     * @throws \InvalidArgumentException When slug is not recognised.
     */
    public function isPluginActive(string $slug): bool
    {
        return $this->registry->isPluginActive($slug);
    }

    /**
     * Returns true if at least one supported LMS plugin is active.
     */
    public function hasAnyActivePlugin(): bool
    {
        return $this->registry->hasAnyActivePlugin();
    }
}
