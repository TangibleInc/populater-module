<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS;

/**
 * Registry for LMS-specific configurations and rendering.
 */
class LmsRegistry
{
    /**
     * Get all registered LMS tabs.
     *
     * @return array<string, \Tangible\Populater\LMS\LmsSettingsTab>
     */
    public static function getTabs(): array
    {
        return apply_filters('tangible_populater_lms_tabs', []);
    }

    /**
     * Get a specific LMS tab by slug.
     *
     * @param string $slug
     * @return \Tangible\Populater\LMS\LmsSettingsTab|null
     */
    public static function getTab(string $slug): ?\Tangible\Populater\LMS\LmsSettingsTab
    {
        $tabs = self::getTabs();
        return $tabs[$slug] ?? null;
    }

    /**
     * @deprecated Use getTabs() and LmsSettingsTab methods instead.
     */
    public static function getPlugins(): array
    {
        $plugins = [];
        foreach (self::getTabs() as $slug => $tab) {
            $plugins[$slug] = [
                'label'       => $tab->getLabel(),
                'description' => $tab->getDescription(),
            ];
        }
        return $plugins;
    }

    /**
     * @deprecated Use getTab($slug)->getDefaultConfig() instead.
     */
    public static function getDefaultConfig(string $slug): array
    {
        $tab = self::getTab($slug);
        return $tab ? $tab->getDefaultConfig() : [];
    }

    /**
     * @deprecated Use getTab($slug)->renderFields() instead.
     */
    public static function getFieldsRenderer(string $slug): ?callable
    {
        $tab = self::getTab($slug);
        if (!$tab) {
            return null;
        }
        return fn() => $tab->renderFields();
    }

    /**
     * @deprecated Use getTab($slug)->supportsGroups() instead.
     */
    public static function supportsGroups(string $slug): bool
    {
        $tab = self::getTab($slug);
        return $tab ? $tab->supportsGroups() : false;
    }
}

