<?php

/**
 * Bootstrap for WordPress integration tests (run inside the wp-test container).
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

$wpLoad = false !== getenv( 'WP_LOAD_PATH' ) && '' !== getenv( 'WP_LOAD_PATH' ) ? (string) getenv( 'WP_LOAD_PATH' ) : '/var/www/html/src/wp-load.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Test stub for a WordPress core symbol.

if ( ! is_readable( $wpLoad ) ) {
	fwrite( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing to STDERR in the test bootstrap.
		STDERR,
		"WordPress bootstrap not found at {$wpLoad}. Run integration tests via composer docker:test:integration.\n",
	);
	exit( 1 );
}

require $wpLoad;

$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Test stub for a WordPress core symbol.

if ( is_readable( $autoloader ) ) {
	require_once $autoloader;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Bootstrap script runs in the global scope.
foreach (
	array(
		'lifterlms/lifterlms.php',
		'lifterlms-groups/lifterlms-groups.php',
	) as $integrationPlugin
) {
	$pluginPath = WP_PLUGIN_DIR . '/' . $integrationPlugin; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Test stub for a WordPress core symbol.

	if ( is_readable( $pluginPath ) && function_exists( 'activate_plugin' ) && ! is_plugin_active( $integrationPlugin ) ) {
		activate_plugin( $pluginPath );
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// LifterLMS ships an older WP_Background_Process that calls wp_die() directly after handle().
add_filter(
	'wp_die_handler',
	static fn(): callable => static function (): void {
	// Suppress background-process exit during synchronous test draining.
	}
);

// Allow synchronous background processing during integration tests.
add_filter( 'tangible_populater_seconds_between_batches', static fn(): int => 0 );

foreach ( array( 'seed_learndash', 'seed_lifterlms', 'seed_tangible_lms' ) as $action ) {
	add_filter( "tangible_populater_{$action}_wp_die", static fn(): bool => false );
	add_filter( "tangible_populater_{$action}_seconds_between_batches", static fn(): int => 0 );
	add_filter( "tangible_populater_{$action}_pre_dispatch", static fn(): bool => true );
}
