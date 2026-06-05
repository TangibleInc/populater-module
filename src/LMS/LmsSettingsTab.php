<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS;

/**
 * Abstract class for LMS settings tabs.
 * Third-party developers can extend this to add support for new LMS plugins.
 */
abstract class LmsSettingsTab
{
    /**
     * The unique slug for the LMS (e.g., 'learndash').
     */
    abstract public function getSlug(): string;

    /**
     * The display label for the tab.
     */
    abstract public function getLabel(): string;

    /**
     * The description of the LMS structure.
     */
    abstract public function getDescription(): string;

    /**
     * The default seeding configuration for this LMS.
     *
     * @return array<string, mixed>
     */
    abstract public function getDefaultConfig(): array;

    /**
     * Whether this LMS supports groups.
     */
    abstract public function supportsGroups(): bool;

    /**
     * Render the LMS-specific fields in the settings page.
     */
    abstract public function renderFields(): void;
}
