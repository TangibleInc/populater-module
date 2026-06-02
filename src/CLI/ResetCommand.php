<?php

declare(strict_types=1);

namespace Tangible\Populater\CLI;

use Tangible\Populater\Database\DatabaseReset;

/**
 * WP-CLI command for database reset.
 *
 * Usage:
 *   wp tangible-populater reset [--yes]
 */
class ResetCommand
{
    public function __construct(private readonly DatabaseReset $dbReset) {}

    /**
     * Removes seeded LMS content while preserving site configuration.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *   wp tangible-populater reset --yes
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function reset(array $args, array $assocArgs): void
    {
        if (!$this->dbReset->isSafeEnvironment()) {
            \WP_CLI::error('Database reset is only allowed in local, development, or staging environments (WP_ENVIRONMENT_TYPE).');
            return;
        }

        $skipConfirmation = isset($assocArgs['yes']) && $assocArgs['yes'] !== false;

        if (!$skipConfirmation) {
            \WP_CLI::confirm('⚠  This will delete posts, non-admin users, plugin/LMS data, custom site roles, and non-core options. Administrators, active plugins, and the active theme will be kept. Continue?');
        }

        \WP_CLI::line('Resetting database…');

        $success = $this->dbReset->reset(confirmed: true);

        if ($success) {
            \WP_CLI::success('Seeded content has been removed. Administrators, plugins, and theme were preserved.');
        } else {
            \WP_CLI::warning('Database reset did not complete successfully.');
        }
    }
}
