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
     * Resets the WordPress database to a clean installation.
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
            \WP_CLI::confirm('⚠  This will permanently DELETE all database tables and re-install WordPress. Continue?');
        }

        \WP_CLI::line('Resetting database…');

        $success = $this->dbReset->reset(confirmed: true);

        if ($success) {
            \WP_CLI::success('Database has been reset to a fresh WordPress installation.');
        } else {
            \WP_CLI::warning('Database reset did not complete successfully.');
        }
    }
}
