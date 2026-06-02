<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Distributes item indices evenly across a number of groups.
 */
final class GroupIndexResolver
{
    public static function resolve(int $itemIndex, int $totalItems, int $totalGroups): int
    {
        if ($totalGroups <= 0 || $totalItems <= 0 || $itemIndex <= 0) {
            return 0;
        }

        $totalGroups = max(1, min($totalGroups, $totalItems));

        return (int) min($totalGroups, max(1, (int) ceil($itemIndex * $totalGroups / $totalItems)));
    }
}
