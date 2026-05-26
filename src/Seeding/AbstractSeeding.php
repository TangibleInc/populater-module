<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\Logger;

/**
 * Abstract base for the seeding process.
 *
 * Concrete subclasses may override processItem() to add custom logic around
 * each queued item (e.g. rate-limiting, extra validation, event emission).
 *
 * Lifecycle
 * ---------
 * 1. start()  — enqueues items and schedules the first batch via WP Cron.
 * 2. A cron event fires processBatch() which calls processItem() per item.
 * 3. cancel() — marks the process as cancelled so the next batch iteration aborts.
 * 4. getStatus() / getLogs() — read persisted state at any time.
 */
abstract class AbstractSeeding
{
    private const OPTION_PREFIX_STATUS = 'tangible_populater_status_';
    private const OPTION_PREFIX_QUEUE  = 'tangible_populater_queue_';
    private const CRON_HOOK            = 'tangible_populater_process_batch';
    private const BATCH_SIZE           = 10;

    public function __construct(protected readonly AbstractSeeder $seeder) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Starts a new seeding process.
     *
     * @param  array<string, mixed> $config  Keys: courses, lessons_per_course, quizzes_per_lesson, users.
     * @return string  Process ID (UUID).
     */
    public function start(array $config): string
    {
        $processId = wp_generate_uuid4();
        $queue     = $this->seeder->buildSeedQueue($config);

        $this->saveStatus($processId, [
            'status'    => SeedingStatus::STATUS_PENDING,
            'total'     => count($queue),
            'processed' => 0,
            'error'     => null,
        ]);

        update_option(self::OPTION_PREFIX_QUEUE . $processId, $queue, false);

        // Schedule the first batch immediately via WP Cron loopback.
        wp_schedule_single_event(time(), self::CRON_HOOK, [$processId]);

        return $processId;
    }

    /**
     * Processes one batch of queued items for the given process ID.
     * Called by the WP Cron hook.
     */
    public function processBatch(string $processId): void
    {
        $statusData = $this->loadStatusData($processId);

        // Abort if cancelled or already finished.
        if (in_array($statusData['status'] ?? '', [
            SeedingStatus::STATUS_CANCELLED,
            SeedingStatus::STATUS_COMPLETED,
            SeedingStatus::STATUS_FAILED,
        ], true)) {
            return;
        }

        $this->saveStatus($processId, array_merge($statusData, ['status' => SeedingStatus::STATUS_RUNNING]));

        /** @var list<array{type: string, data: array<string, mixed>}> $queue */
        $queue   = get_option(self::OPTION_PREFIX_QUEUE . $processId, []);
        $logger  = new Logger($processId);
        $batch   = array_splice($queue, 0, self::BATCH_SIZE);

        foreach ($batch as $item) {
            // Re-check for cancellation between items.
            $fresh = $this->loadStatusData($processId);
            if (($fresh['status'] ?? '') === SeedingStatus::STATUS_CANCELLED) {
                update_option(self::OPTION_PREFIX_QUEUE . $processId, $queue, false);
                return;
            }

            try {
                $this->processItem($item, $processId, $logger);
            } catch (\Throwable $e) {
                $logger->error(sprintf('[%s] %s', $item['type'], $e->getMessage()));
            }

            $statusData['processed'] = ($statusData['processed'] ?? 0) + 1;
            $this->saveStatus($processId, array_merge($statusData, ['status' => SeedingStatus::STATUS_RUNNING]));
        }

        if (empty($queue)) {
            $this->saveStatus($processId, array_merge($statusData, ['status' => SeedingStatus::STATUS_COMPLETED]));
            delete_option(self::OPTION_PREFIX_QUEUE . $processId);
            $logger->info('Seeding completed.');
        } else {
            // Persist remaining queue and schedule next batch.
            update_option(self::OPTION_PREFIX_QUEUE . $processId, $queue, false);
            wp_schedule_single_event(time(), self::CRON_HOOK, [$processId]);
        }
    }

    /**
     * Attempts to cancel a running or pending process.
     */
    public function cancel(string $processId): bool
    {
        $statusData = $this->loadStatusData($processId);
        $status     = SeedingStatus::fromArray($processId, $statusData);

        if (!$status->canBeCancelled()) {
            return false;
        }

        $this->saveStatus($processId, array_merge($statusData, ['status' => SeedingStatus::STATUS_CANCELLED]));
        return true;
    }

    /**
     * Returns the current status of a process.
     */
    public function getStatus(string $processId): SeedingStatus
    {
        return SeedingStatus::fromArray($processId, $this->loadStatusData($processId));
    }

    /**
     * Returns the log entries for a process.
     *
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        $logger = new Logger($processId);
        return $logger->getEntries();
    }

    // -------------------------------------------------------------------------
    // Extension point
    // -------------------------------------------------------------------------

    /**
     * Processes a single queued item.
     *
     * The default implementation delegates to the seeder.
     * Override in a subclass to add custom behaviour.
     *
     * @param array{type: string, data: array<string, mixed>} $item
     */
    protected function processItem(array $item, string $processId, Logger $logger): void
    {
        $type   = $item['type'];
        $data   = $item['data'];
        $logger->info("Processing $type: " . json_encode($data));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function saveStatus(string $processId, array $data): void
    {
        update_option(self::OPTION_PREFIX_STATUS . $processId, $data, false);
    }

    /** @return array<string, mixed> */
    private function loadStatusData(string $processId): array
    {
        $data = get_option(self::OPTION_PREFIX_STATUS . $processId, null);
        return is_array($data) ? $data : [];
    }
}
