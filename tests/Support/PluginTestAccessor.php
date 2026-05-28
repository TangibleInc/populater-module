<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Tangible\Populater\Plugin;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\WpBackgroundSeedingRunner;

/**
 * Access plugin internals from integration tests.
 */
final class PluginTestAccessor
{
    public static function seedingManager(): SeedingManager
    {
        $plugin = Plugin::getInstance();
        $ref    = new \ReflectionProperty($plugin, 'seedingManager');
        $ref->setAccessible(true);

        /** @var SeedingManager $manager */
        $manager = $ref->getValue($plugin);

        return $manager;
    }

    public static function processForPlugin(string $slug): AbstractSeeding
    {
        $manager = self::seedingManager();
        $manager->registerBackgroundProcesses();

        $ref = new \ReflectionProperty(SeedingManager::class, 'runners');
        $ref->setAccessible(true);

        /** @var array<string, WpBackgroundSeedingRunner> $runners */
        $runners = $ref->getValue($manager);

        if (!isset($runners[$slug])) {
            throw new \RuntimeException(sprintf('No seeding process registered for plugin "%s".', $slug));
        }

        $runnerRef = new \ReflectionProperty(WpBackgroundSeedingRunner::class, 'process');
        $runnerRef->setAccessible(true);

        /** @var AbstractSeeding $process */
        $process = $runnerRef->getValue($runners[$slug]);

        return $process;
    }
}
