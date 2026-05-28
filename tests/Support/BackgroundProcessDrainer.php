<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;

/**
 * Processes background seeding queues synchronously during integration tests.
 */
final class BackgroundProcessDrainer
{
    public static function runSeedToCompletion(SeedingManager $manager, string $pluginSlug, string $processId): SeedingStatus
    {
        $process  = PluginTestAccessor::processForPlugin($pluginSlug);
        $task     = new \ReflectionMethod($process, 'task');
        $task->setAccessible(true);
        $getBatch = new \ReflectionMethod(AbstractSeeding::class, 'get_batch');
        $getBatch->setAccessible(true);
        $delete   = new \ReflectionMethod(AbstractSeeding::class, 'delete');
        $delete->setAccessible(true);
        $update   = new \ReflectionMethod(AbstractSeeding::class, 'update');
        $update->setAccessible(true);

        $guard = 0;

        while ($guard++ < 500) {
            $status = $manager->getStatus($processId);

            if (self::isTerminal($status)) {
                return $status;
            }

            $batch = $getBatch->invoke($process);

            if ($batch === false || !isset($batch->data) || !is_array($batch->data) || $batch->data === []) {
                break;
            }

            foreach ($batch->data as $key => $item) {
                $task->invoke($process, $item);
                unset($batch->data[$key]);
            }

            if ($batch->data !== []) {
                $update->invoke($process, $batch->key, $batch->data);
            } else {
                $delete->invoke($process, $batch->key);
            }
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
