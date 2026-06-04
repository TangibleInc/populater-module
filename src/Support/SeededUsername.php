<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Deterministic WordPress login names for seeded LMS users.
 *
 * Prefixes are plugin-specific so bench accounts are easy to spot in logs
 * (e.g. lifterstudent99, ldstudent2). Each plugin’s k6 stress test hardcodes
 * the matching student prefix (see k6/lifterlms-stress.js, k6/learndash-stress.js).
 */
final class SeededUsername
{
    public static function username(string $pluginSlug, string $roleType, int $index): string
    {
        return self::prefix($pluginSlug, $roleType) . $index;
    }

    public static function email(string $pluginSlug, string $roleType, int $index): string
    {
        return self::username($pluginSlug, $roleType, $index) . '@example.com';
    }

    public static function prefix(string $pluginSlug, string $roleType): string
    {
        return match ($pluginSlug) {
            'lifterlms' => match ($roleType) {
                'groupadmin' => 'liftergroupadmin',
                default      => 'lifterstudent',
            },
            'learndash' => match ($roleType) {
                'groupadmin' => 'ldgroupadmin',
                default      => 'ldstudent',
            },
            'tangible-lms' => match ($roleType) {
                'groupadmin' => 'tlmsgroupadmin',
                default      => 'tlmsstudent',
            },
            default => $roleType,
        };
    }
}
