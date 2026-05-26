<?php

declare(strict_types=1);

namespace Tangible\Populater;

/**
 * Detects which supported LMS plugins are active.
 */
class PluginDetector
{
    /**
     * Map of plugin slugs to their main plugin files.
     *
     * @var array<string, string>
     */
    private const PLUGIN_FILES = [
        'learndash'   => 'sfwd-lms/sfwd_lms.php',
        'lifterlms'   => 'lifterlms/lifterlms.php',
        'tangible-lms' => 'tangible-lms/tangible-lms.php',
    ];

    /**
     * Map of plugin slugs to human-readable names.
     *
     * @var array<string, string>
     */
    private const PLUGIN_NAMES = [
        'learndash'   => 'LearnDash LMS',
        'lifterlms'   => 'LifterLMS',
        'tangible-lms' => 'Tangible LMS',
    ];

    /**
     * Returns all supported plugin slugs and their metadata.
     *
     * @return array<string, array{name: string, file: string}>
     */
    public function getSupportedPlugins(): array
    {
        $plugins = [];
        foreach (self::PLUGIN_FILES as $slug => $file) {
            $plugins[$slug] = [
                'name' => self::PLUGIN_NAMES[$slug],
                'file' => $file,
            ];
        }
        return $plugins;
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
        if (!isset(self::PLUGIN_FILES[$slug])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown plugin slug "%s". Supported: %s', $slug, implode(', ', array_keys(self::PLUGIN_FILES)))
            );
        }

        return is_plugin_active(self::PLUGIN_FILES[$slug]);
    }

    /**
     * Returns true if at least one supported LMS plugin is active.
     */
    public function hasAnyActivePlugin(): bool
    {
        foreach (array_keys(self::PLUGIN_FILES) as $slug) {
            if ($this->isPluginActive($slug)) {
                return true;
            }
        }
        return false;
    }
}
