<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;

/**
 * Processes background seeding queues synchronously during integration tests.
 */
final class BackgroundProcessDrainer
{
    public static function runSeedToCompletion(SeedingManager $manager, string $pluginSlug, string $processId): SeedingStatus
    {
        $process = PluginTestAccessor::processForPlugin($pluginSlug);
        $handle  = new \ReflectionMethod($process, 'handle');
        $handle->setAccessible(true);

        $queueEmpty = new \ReflectionMethod($process, 'is_queue_empty');
        $queueEmpty->setAccessible(true);

        $guard = 0;

        while ($guard++ < 500) {
            $status = $manager->getStatus($processId);

            if (self::isTerminal($status)) {
                return $status;
            }

            if ($queueEmpty->invoke($process)) {
                break;
            }

            $handle->invoke($process);
        }

        return $manager->getStatus($processId);
    }

    private static function isTerminal(SeedingStatus $status): bool
    {
        return in_array($status->getStatus(), [
            SeedingStatus::STATUS_COMPLETED,
            SeedingStatus::STATUS_FAILED,
            SeedingStatus::STATUS_CANCELLED,
        ], true);
    }
}
