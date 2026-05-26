<?php

declare(strict_types=1);

namespace Tangible\Populater\CLI;

use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;

/**
 * WP-CLI commands for seeding content.
 *
 * Usage:
 *   wp tangible-populater seed <plugin> [--courses=<n>] [--lessons=<n>] [--quizzes=<n>] [--users=<n>] [--wait]
 *   wp tangible-populater seed status <process-id>
 *   wp tangible-populater seed logs   <process-id>
 *   wp tangible-populater seed cancel <process-id>
 */
class SeedCommand
{
    public function __construct(private readonly SeedingManager $manager) {}

    /**
     * Starts a seeding process.
     *
     * ## OPTIONS
     *
     * <plugin>
     * : Plugin slug to seed (learndash | lifterlms | tangible-lms).
     *
     * [--courses=<n>]
     * : Number of courses to create. Default: 5.
     *
     * [--lessons=<n>]
     * : Lessons per course. Default: 5.
     *
     * [--quizzes=<n>]
     * : Quizzes per lesson. Default: 1.
     *
     * [--users=<n>]
     * : Number of users to create. Default: 10.
     *
     * [--wait]
     * : Wait for the process to complete, polling every second.
     *
     * ## EXAMPLES
     *
     *   wp tangible-populater seed learndash --courses=10 --wait
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function seed(array $args, array $assocArgs): void
    {
        $plugin = $args[0] ?? '';

        $config = [
            'plugin'            => $plugin,
            'courses'           => (int) ($assocArgs['courses'] ?? 5),
            'lessons_per_course' => (int) ($assocArgs['lessons']  ?? 5),
            'quizzes_per_lesson' => (int) ($assocArgs['quizzes']  ?? 1),
            'users'             => (int) ($assocArgs['users']   ?? 10),
        ];

        try {
            $processId = $this->manager->start($config);
        } catch (\Throwable $e) {
            \WP_CLI::error($e->getMessage());
            return;
        }

        \WP_CLI::success("Seeding started. Process ID: $processId");

        $shouldWait = isset($assocArgs['wait']) && $assocArgs['wait'] !== false;

        if (!$shouldWait) {
            \WP_CLI::line('Run `wp tangible-populater seed status ' . $processId . '` to check progress.');
            return;
        }

        $this->pollUntilDone($processId);
    }

    /**
     * Displays the status of a seeding process.
     *
     * ## OPTIONS
     *
     * <process-id>
     * : The process ID returned by the seed command.
     *
     * [--format=<format>]
     * : Output format (table | json | csv). Default: table.
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function status(array $args, array $assocArgs): void
    {
        $processId = $args[0] ?? '';
        $status    = $this->manager->getStatus($processId);
        $format    = $assocArgs['format'] ?? 'table';

        \WP_CLI\Utils\format_items($format, [$status->toArray()], array_keys($status->toArray()));
    }

    /**
     * Displays log entries for a seeding process.
     *
     * ## OPTIONS
     *
     * <process-id>
     * : The process ID.
     *
     * [--format=<format>]
     * : Output format (table | json | csv). Default: table.
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function logs(array $args, array $assocArgs): void
    {
        $processId = $args[0] ?? '';
        $logs      = $this->manager->getLogs($processId);
        $format    = $assocArgs['format'] ?? 'table';

        if (empty($logs)) {
            \WP_CLI::line('No log entries found.');
            return;
        }

        \WP_CLI\Utils\format_items($format, $logs, ['timestamp', 'level', 'message']);
    }

    /**
     * Cancels a running seeding process.
     *
     * ## OPTIONS
     *
     * <process-id>
     * : The process ID to cancel.
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function cancel(array $args, array $assocArgs): void
    {
        $processId = $args[0] ?? '';

        if ($this->manager->cancel($processId)) {
            \WP_CLI::success("Process $processId has been cancelled.");
        } else {
            \WP_CLI::error("Could not cancel process $processId (already completed, failed, or not found).");
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function pollUntilDone(string $processId): void
    {
        $status   = $this->manager->getStatus($processId);
        $progress = \WP_CLI\Utils\make_progress_bar('Seeding…', $status->getTotal() ?: 1);

        while (true) {
            $status = $this->manager->getStatus($processId);

            $progress->tick($status->getProcessed());

            if (!$status->isRunning() && $status->getStatus() !== SeedingStatus::STATUS_PENDING) {
                break;
            }

            sleep(1);
        }

        $progress->finish();

        match ($status->getStatus()) {
            SeedingStatus::STATUS_COMPLETED => \WP_CLI::success('Seeding completed.'),
            SeedingStatus::STATUS_CANCELLED => \WP_CLI::warning('Seeding was cancelled.'),
            SeedingStatus::STATUS_FAILED    => \WP_CLI::error('Seeding failed: ' . ($status->getError() ?? 'unknown error')),
            default                         => \WP_CLI::line('Final status: ' . $status->getStatus()),
        };
    }
}
