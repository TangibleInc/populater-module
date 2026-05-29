<?php

declare(strict_types=1);

/**
 * Bootstrap for WordPress integration tests (run inside the wp-test container).
 */

$wpLoad = getenv('WP_LOAD_PATH') ?: '/var/www/html/src/wp-load.php';

if (!is_readable($wpLoad)) {
    fwrite(
        STDERR,
        "WordPress bootstrap not found at {$wpLoad}. Run integration tests via composer docker:test:integration.\n",
    );
    exit(1);
}

require $wpLoad;

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (is_readable($autoloader)) {
    require_once $autoloader;
}

// LifterLMS ships an older WP_Background_Process that calls wp_die() directly after handle().
add_filter('wp_die_handler', static fn(): callable => static function (): void {
    // Suppress background-process exit during synchronous test draining.
});

// Allow synchronous background processing during integration tests.
add_filter('tangible_populater_seconds_between_batches', static fn(): int => 0);

foreach (['seed_learndash', 'seed_lifterlms', 'seed_tangible_lms'] as $action) {
    add_filter("tangible_populater_{$action}_wp_die", static fn(): bool => false);
    add_filter("tangible_populater_{$action}_seconds_between_batches", static fn(): int => 0);
    add_filter("tangible_populater_{$action}_pre_dispatch", static fn(): bool => true);
}
