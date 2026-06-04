<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Whether an LMS supports seeding groups for the admin UI and queue builder.
 */
final class LmsGroupsCapability
{
    public static function supports(string $pluginSlug): bool
    {
        return match ($pluginSlug) {
            'learndash' => true,
            'lifterlms' => self::lifterGroupsAddonActive(),
            default     => false,
        };
    }

    public static function lifterGroupsAddonActive(): bool
    {
        return function_exists('llms_groups')
            || class_exists('LLMS_Groups_Enrollment', false);
    }
}
