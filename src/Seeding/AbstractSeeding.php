<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\Logger;

/**
 * Abstract base for the seeding process.
 *
 * Extends WP Background Processing so each run is queued and processed
 * asynchronously. Concrete subclasses may override processItem() to add custom
 * logic (e.g. LMS-specific step classes).
 *
 * Lifecycle
 * ---------
 * 1. start()  — enqueues items and dispatches the background process.
 * 2. task()   — processes each queued item within server time/memory limits.
 * 3. cancelProcess() — marks the process cancelled and clears the background queue.
 * 4. getStatus() / getLogs() — read persisted state at any time.
 */
abstract class AbstractSeeding extends \WP_Background_Process
{
    protected $prefix = 'tangible_populater';

    protected $action = 'seed';

    /** @var bool|array */
    protected $allowed_batch_data_classes = false;

    private const OPTION_PREFIX_STATUS = 'tangible_populater_status_';

    public function __construct(
        protected readonly AbstractSeeder $seeder,
    ) {
        parent::__construct(false);
    }

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

        $this->saveStatusForProcess($processId, [
            'status'    => SeedingStatus::STATUS_PENDING,
            'plugin'    => $this->seeder->getSlug(),
            'total'     => count($queue),
            'processed' => 0,
            'error'     => null,
        ]);

        foreach ($queue as $item) {
            $this->push_to_queue([
                'process_id' => $processId,
                'type'       => $item['type'],
                'data'       => $item['data'],
            ]);
        }

        $this->save()->dispatch();

        return $processId;
    }

    /**
     * Attempts to cancel a running or pending process.
     */
    public function cancelProcess(string $processId): bool
    {
        $statusData = $this->loadStatusForProcess($processId);
        $status     = SeedingStatus::fromArray($processId, $statusData);

        if (!$status->canBeCancelled()) {
            return false;
        }

        $this->saveStatusForProcess($processId, array_merge($statusData, ['status' => SeedingStatus::STATUS_CANCELLED]));
        $this->cancelBackgroundQueue();
        SeedingIdMap::delete($processId);

        return true;
    }

    /**
     * Returns the current status of a process.
     */
    public function getStatus(string $processId): SeedingStatus
    {
        return SeedingStatus::fromArray($processId, $this->loadStatusForProcess($processId));
    }

    /**
     * Returns the log entries for a process.
     *
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return (new Logger($processId))->getEntries();
    }

    // -------------------------------------------------------------------------
    // WP_Background_Process
    // -------------------------------------------------------------------------

    /**
     * @param array{process_id: string, type: string, data: array<string, mixed>} $item
     *
     * @return false
     */
    protected function task($item)
    {
        $processId = (string) ($item['process_id'] ?? '');

        if ($processId === '') {
            return false;
        }

        $statusData = $this->loadStatusForProcess($processId);

        if (($statusData['status'] ?? '') === SeedingStatus::STATUS_CANCELLED) {
            return false;
        }

        if (($statusData['status'] ?? '') === SeedingStatus::STATUS_PENDING) {
            $this->saveStatusForProcess($processId, array_merge($statusData, [
                'status' => SeedingStatus::STATUS_RUNNING,
            ]));
        }

        $logger    = new Logger($processId);
        $queueItem = [
            'type' => $item['type'],
            'data' => SeedingIdMap::enrich($processId, $item['type'], $item['data']),
        ];

        try {
            $this->processItem($queueItem, $processId, $logger);
        } catch (\Throwable $e) {
            $logger->error(sprintf('[%s] %s', $queueItem['type'], $e->getMessage()));
        }

        $statusData = $this->loadStatusForProcess($processId);
        $processed  = ($statusData['processed'] ?? 0) + 1;
        $total      = (int) ($statusData['total'] ?? 0);

        $statusData['processed'] = $processed;
        $statusData['status']    = $processed >= $total && $total > 0
            ? SeedingStatus::STATUS_COMPLETED
            : SeedingStatus::STATUS_RUNNING;

        $this->saveStatusForProcess($processId, $statusData);

        if ($statusData['status'] === SeedingStatus::STATUS_COMPLETED) {
            SeedingIdMap::delete($processId);
            $logger->info('Seeding completed.');
        }

        return false;
    }

    protected function complete(): void
    {
        parent::complete();
    }

    protected function cancelled(): void
    {
        parent::cancelled();
    }

    // -------------------------------------------------------------------------
    // Extension point
    // -------------------------------------------------------------------------

    /**
     * Processes a single queued item.
     *
     * Override in a subclass to add custom behaviour.
     *
     * @param array{type: string, data: array<string, mixed>} $item
     */
    protected function processItem(array $item, string $processId, Logger $logger): void
    {
        $type = $item['type'];
        $data = $item['data'];

        $logger->info(sprintf('Processing %s (process: %s)', $type, $processId));

        match ($type) {
            'course'      => SeedingIdMap::record($processId, $type, $data, $this->seeder->seedCourses(1, $data)),
            'lesson'      => SeedingIdMap::record($processId, $type, $data, $this->seeder->seedLessons(1, (int) ($data['course_id'] ?? 0), $data)),
            'quiz'        => $this->seeder->seedQuizzes(1, (int) ($data['lesson_id'] ?? 0), $data),
            'user'        => $this->seeder->seedUsers(1, $data),
            'certificate' => $this->seeder->seedCertificates(1, $data),
            default       => $logger->warning(sprintf('Unknown item type: %s', $type)),
        };
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function cancelBackgroundQueue(): void
    {
        parent::cancel();
    }

    /** @param array<string, mixed> $data */
    protected function saveStatusForProcess(string $processId, array $data): void
    {
        update_option(self::OPTION_PREFIX_STATUS . $processId, $data, false);
    }

    /** @return array<string, mixed> */
    protected function loadStatusForProcess(string $processId): array
    {
        $data = get_option(self::OPTION_PREFIX_STATUS . $processId, null);

        return is_array($data) ? $data : [];
    }
}
