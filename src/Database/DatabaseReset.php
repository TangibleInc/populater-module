<?php

declare(strict_types=1);

namespace Tangible\Populater\Database;

/**
 * Resets the WordPress database to a clean installation state.
 *
 * CAUTION: This is a destructive, irreversible operation.
 * It is intentionally gated behind:
 *  - An environment safety check (WP_ENVIRONMENT_TYPE or WP_DEBUG must signal non-production).
 *  - An explicit confirmed: true parameter so callers must opt in.
 */
class DatabaseReset
{
    /**
     * Drops all tables and re-runs the WordPress installer.
     *
     * @param  bool  $confirmed  Must be true or the operation is aborted.
     * @return bool  True on success, false on failure or if not confirmed.
     */
    public function reset(bool $confirmed = false): bool
    {
        if (!$confirmed) {
            return false;
        }

        if (!$this->isSafeEnvironment()) {
            return false;
        }

        $this->dropAllTables();
        $this->reinstallWordPress();

        do_action('tangible_populater_database_reset');

        return true;
    }

    /**
     * Returns true when it is safe to perform a destructive database reset.
     *
     * Safe environments: local, development, staging, or when WP_DEBUG is true.
     * Production is blocked unless WP_ENVIRONMENT_TYPE is explicitly overridden
     * to one of the safe values.
     */
    public function isSafeEnvironment(): bool
    {
        $envType = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : '';

        if (in_array($envType, ['local', 'development', 'staging'], true)) {
            return true;
        }

        // Allow when running under WP-CLI in debug mode (typical for dev tooling).
        if (defined('WP_CLI') && WP_CLI && defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return false;
    }

    /**
     * Returns the list of table names that would be dropped.
     *
     * @return list<string>
     */
    public function getTablesToReset(): array
    {
        global $wpdb;

        /** @var list<string> $tables */
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array($tables) ? $tables : [];
    }

    // -------------------------------------------------------------------------
    // Protected helpers — public for test mocking via getMockBuilder::onlyMethods
    // -------------------------------------------------------------------------

    public function dropAllTables(): void
    {
        global $wpdb;

        $tables = $this->getTablesToReset();

        // Temporarily disable foreign key checks to allow dropping in any order.
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    public function reinstallWordPress(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Re-runs the core schema SQL (CREATE TABLE IF NOT EXISTS …).
        /** @psalm-suppress UndefinedFunction */
        dbDelta(wp_get_db_schema());

        // Restore the site URL and admin email options that wp_install() would set.
        if (function_exists('wp_install')) {
            // wp_install is only available after upgrade.php is loaded.
            $adminEmail = defined('WP_TESTS_EMAIL') ? WP_TESTS_EMAIL : 'admin@example.com';
            $title      = defined('WP_TESTS_TITLE') ? WP_TESTS_TITLE : 'Test Site';
            wp_install($title, 'admin', $adminEmail, true);
        }
    }
}
