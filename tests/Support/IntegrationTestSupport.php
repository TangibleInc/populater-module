<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

/**
 * Helpers for isolating integration tests from async seeding side effects.
 */
final class IntegrationTestSupport
{
    /** @var list<string> */
    private const SEED_ACTIONS = [
        'seed_learndash',
        'seed_lifterlms',
        'seed_tangible_lms',
    ];

    public static function clearBackgroundSeedingState(): void
    {
        foreach (self::SEED_ACTIONS as $action) {
            $identifier = 'tangible_populater_' . $action;
            wp_clear_scheduled_hook($identifier . '_cron');

            if (function_exists('wp_unschedule_hook')) {
                wp_unschedule_hook($identifier . '_cron');
            }
        }
    }
}
