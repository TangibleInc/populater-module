<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Fills WordPress user profile fields for seeded accounts.
 */
final class SeededUserProfile
{
    public static function populate(int $userId, int $index, string $roleType = 'student'): void
    {
        if ($userId <= 0 || !function_exists('update_user_meta')) {
            return;
        }

        [$firstName, $lastName, $displayName] = self::names($index, $roleType);

        update_user_meta($userId, 'first_name', $firstName);
        update_user_meta($userId, 'last_name', $lastName);

        if (function_exists('wp_update_user')) {
            wp_update_user([
                'ID'           => $userId,
                'display_name' => $displayName,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function names(int $index, string $roleType): array
    {
        if ($roleType === 'groupadmin') {
            return [
                'Group',
                "Admin {$index}",
                "Group Admin {$index}",
            ];
        }

        return [
            'Student',
            (string) $index,
            "Student {$index}",
        ];
    }
}
