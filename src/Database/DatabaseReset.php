<?php

declare(strict_types=1);

namespace Tangible\Populater\Database;

/**
 * Resets seeded LMS content while preserving site configuration.
 *
 * CAUTION: This is a destructive operation for posts, non-admin users, plugin
 * options, custom LMS roles, and custom LMS tables. It is intentionally gated behind:
 *  - An environment safety check (WP_ENVIRONMENT_TYPE or WP_DEBUG must signal non-production).
 *  - An explicit confirmed: true parameter so callers must opt in.
 */
class DatabaseReset
{
    /** @var list<string> */
    private const CORE_TABLES = [
        'commentmeta',
        'comments',
        'links',
        'options',
        'postmeta',
        'posts',
        'term_relationships',
        'term_taxonomy',
        'termmeta',
        'terms',
        'usermeta',
        'users',
    ];

    /**
     * Removes seeded content and plugin data while keeping admins, active plugins,
     * the active theme, and core WordPress options.
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

        $adminUserIds = $this->getAdministratorUserIds();

        if ($adminUserIds === []) {
            return false;
        }

        $this->deletePostsAndComments();
        $this->resetTaxonomies();
        $this->deleteNonAdminUsers($adminUserIds);
        $this->cleanupOptions();
        $this->resetSiteRoles();
        $this->restorePreservedAdministrators($adminUserIds);
        $this->truncateCustomTables();
        $this->finalizeSiteState();

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
        $envType = function_exists('wp_get_environment_type')
            ? wp_get_environment_type()
            : (defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : '');

        if (in_array($envType, ['local', 'development', 'staging'], true)) {
            return true;
        }

        // Allow when debug mode is enabled (typical for dev tooling).
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return false;
    }

    /**
     * Returns the list of table names in the current WordPress prefix.
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

    /** @return list<int> */
    public function getAdministratorUserIds(): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $queryArgs = [
            'fields' => 'ID',
            'number' => -1,
        ];

        $byRole = get_users(array_merge($queryArgs, ['role' => 'administrator']));
        $byCapability = get_users(array_merge($queryArgs, ['capability' => 'manage_options']));

        $ids = array_merge(
            array_map('intval', $byRole),
            array_map('intval', $byCapability),
        );

        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0,
        )));

        sort($ids);

        return $ids;
    }

    // -------------------------------------------------------------------------
    // Cleanup helpers — public for test mocking via getMockBuilder::onlyMethods
    // -------------------------------------------------------------------------

    public function deletePostsAndComments(): void
    {
        global $wpdb;

        foreach (['postmeta', 'posts', 'commentmeta', 'comments', 'links'] as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public function resetTaxonomies(): void
    {
        global $wpdb;

        foreach (['term_relationships', 'termmeta', 'term_taxonomy', 'terms'] as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        if (!function_exists('wp_insert_term')) {
            return;
        }

        $term = wp_insert_term('Uncategorized', 'category', ['slug' => 'uncategorized']);

        if (is_wp_error($term)) {
            return;
        }

        $termId = (int) $term['term_id'];
        update_option('default_category', $termId);
        update_option('default_email_category', $termId);
    }

    /** @param list<int> $adminUserIds */
    public function deleteNonAdminUsers(array $adminUserIds): void
    {
        global $wpdb;

        if ($adminUserIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($adminUserIds), '%d'));

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN ({$placeholders})",
                ...$adminUserIds
            )
        );

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare(
                "DELETE FROM {$wpdb->users} WHERE ID NOT IN ({$placeholders})",
                ...$adminUserIds
            )
        );
    }

    public function cleanupOptions(): void
    {
        global $wpdb;

        /** @var list<string>|null $optionNames */
        $optionNames = $wpdb->get_col("SELECT option_name FROM {$wpdb->options}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

        if (!is_array($optionNames)) {
            return;
        }

        foreach ($optionNames as $optionName) {
            if (!WordPressCoreOptions::shouldPreserve($optionName)) {
                delete_option($optionName);
            }
        }
    }

    /**
     * Restores WordPress default roles after plugin/LMS roles were removed.
     *
     * The roles option ({prefix}user_roles) is not preserved during cleanupOptions,
     * so LMS-specific roles are wiped. This repopulates core roles (administrator,
     * editor, author, contributor, subscriber) for a clean slate.
     */
    public function resetSiteRoles(): void
    {
        global $wpdb, $wp_roles, $wp_user_roles;

        if (!function_exists('populate_roles')) {
            $schema = ABSPATH . 'wp-admin/includes/schema.php';

            if (!is_readable($schema)) {
                return;
            }

            require_once $schema;
        }

        // cleanupOptions() deletes the DB option but WordPress keeps roles in
        // $wp_user_roles for the rest of the request. WP_Roles sets use_db=false
        // when that global is non-empty, so populate_roles() would not persist.
        $wp_user_roles = [];
        $wp_roles       = null;

        delete_option($wpdb->prefix . 'user_roles');

        populate_roles();
    }

    /**
     * Re-applies the administrator role to every preserved admin account.
     *
     * After resetSiteRoles(), user capability meta may still reference LMS roles
     * that no longer exist. set_role() writes a clean administrator assignment
     * so every preserved account keeps full admin access, not just the default user.
     *
     * @param list<int> $adminUserIds
     */
    public function restorePreservedAdministrators(array $adminUserIds): void
    {
        if (!function_exists('get_user_by')) {
            return;
        }

        foreach ($adminUserIds as $userId) {
            $user = get_user_by('id', $userId);

            if (!$user instanceof \WP_User) {
                continue;
            }

            $user->set_role('administrator');
        }
    }

    public function truncateCustomTables(): void
    {
        global $wpdb;

        $coreTables = array_map(
            static fn (string $suffix): string => $wpdb->prefix . $suffix,
            self::CORE_TABLES
        );

        foreach ($this->getTablesToReset() as $table) {
            if (in_array($table, $coreTables, true)) {
                continue;
            }

            $wpdb->query("TRUNCATE TABLE `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public function finalizeSiteState(): void
    {
        update_option('sticky_posts', []);
        update_option('page_on_front', 0);
        update_option('page_for_posts', 0);
        update_option('wp_page_for_privacy_policy', 0);
        update_option('rewrite_rules', '');

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        if (function_exists('delete_expired_transients')) {
            delete_expired_transients(true);
        }
    }
}
