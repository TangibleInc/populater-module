<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\Logger;

/**
 * Abstract base for the seeding process.
 */
abstract class AbstractSeeding extends \WP_Background_Process
{
    protected $prefix = 'tangible_populater';

    protected $action = 'seed';

    /** @var bool|array */
    protected $allowed_batch_data_classes = false;

    private const ERROR_THRESHOLD = 3;

    protected readonly ProcessRepository $repository;

    public function __construct(
        protected readonly AbstractSeeder $seeder,
        ?ProcessRepository $repository = null,
    ) {
        $this->repository = $repository ?? new ProcessRepository();
        parent::__construct(false);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function start(array $config): string
    {
        $processId  = wp_generate_uuid4();
        $seedConfig = SeedConfig::fromArray(array_merge($config, ['plugin' => $this->seeder->getSlug()]));
        $queue      = $this->seeder->buildSeedQueue($seedConfig);

        $this->saveStatusForProcess($processId, [
            'status'    => SeedingStatus::STATUS_PENDING,
            'plugin'    => $this->seeder->getSlug(),
            'total'     => count($queue),
            'processed' => 0,
            'errors'    => 0,
            'error'     => null,
        ]);

        foreach ($queue as $item) {
            $this->push_to_queue([
                'process_id' => $processId,
                'type'       => $item->type,
                'data'       => $item->data,
            ]);
        }

        $this->save()->dispatch();

        return $processId;
    }

    public function cancelProcess(string $processId): bool
    {
        $statusData = $this->loadStatusForProcess($processId);
        $status     = SeedingStatus::fromArray($processId, $statusData);

        if (!$status->canBeCancelled()) {
            return false;
        }

        // Per-process cancel: mark status only; do not cancel the entire LMS queue.
        $this->saveStatusForProcess($processId, array_merge($statusData, [
            'status' => SeedingStatus::STATUS_CANCELLED,
        ]));
        $this->repository->deleteIdMap($processId);

        return true;
    }

    public function getStatus(string $processId): SeedingStatus
    {
        return SeedingStatus::fromArray($processId, $this->loadStatusForProcess($processId));
    }

    /**
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return $this->repository->getLogs($processId);
    }

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
        $status     = $statusData['status'] ?? '';

        if ($status === SeedingStatus::STATUS_CANCELLED) {
            return false;
        }

        if ($status === SeedingStatus::STATUS_FAILED) {
            return false;
        }

        if ($status === SeedingStatus::STATUS_PENDING) {
            $this->saveStatusForProcess($processId, array_merge($statusData, [
                'status' => SeedingStatus::STATUS_RUNNING,
            ]));
            $statusData = $this->loadStatusForProcess($processId);
        }

        $logger    = new Logger($processId);
        $queueItem = [
            'type' => $item['type'],
            'data' => SeedingIdMap::enrich($processId, $item['type'], $item['data'], $this->repository),
        ];

        $errorsBefore = $this->countLogErrors($logger);

        try {
            $this->processItem($queueItem, $processId, $logger);
        } catch (\Throwable $e) {
            $logger->error(sprintf('[%s] %s', $queueItem['type'], $e->getMessage()));
        }

        $statusData = $this->loadStatusForProcess($processId);

        if ($statusData['status'] === SeedingStatus::STATUS_CANCELLED) {
            return false;
        }

        $errors = (int) ($statusData['errors'] ?? 0) + max(0, $this->countLogErrors($logger) - $errorsBefore);

        $processed = ($statusData['processed'] ?? 0) + 1;
        $total     = (int) ($statusData['total'] ?? 0);

        $statusData['processed'] = $processed;
        $statusData['errors']    = $errors;

        if ($errors >= self::ERROR_THRESHOLD) {
            $statusData['status'] = SeedingStatus::STATUS_FAILED;
            $statusData['error']  = $statusData['error'] ?? 'Too many errors during seeding.';
        } elseif ($processed >= $total && $total > 0) {
            $statusData['status'] = SeedingStatus::STATUS_COMPLETED;
        } else {
            $statusData['status'] = SeedingStatus::STATUS_RUNNING;
        }

        $this->saveStatusForProcess($processId, $statusData);

        if ($statusData['status'] === SeedingStatus::STATUS_COMPLETED) {
            $this->repository->deleteIdMap($processId);
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

    /**
     * @param array{type: string, data: array<string, mixed>} $item
     */
    protected function processItem(array $item, string $processId, Logger $logger): void
    {
        $type = $item['type'];
        $data = $item['data'];

        $logger->info(sprintf('Processing %s (process: %s)', $type, $processId));

        match ($type) {
            'course'      => SeedingIdMap::record($processId, $type, $data, $this->seeder->seedCourses(1, $data), $this->repository),
            'lesson'      => SeedingIdMap::record($processId, $type, $data, $this->seeder->seedLessons(1, (int) ($data['course_id'] ?? 0), $data), $this->repository),
            'quiz'        => $this->seeder->seedQuizzes(1, (int) ($data['lesson_id'] ?? 0), $data),
            'user'        => $this->seeder->seedUsers(1, $data),
            'certificate' => $this->seeder->seedCertificates(1, $data),
            default       => $logger->warning(sprintf('Unknown item type: %s', $type)),
        };
    }

    /** @param array<string, mixed> $data */
    protected function saveStatusForProcess(string $processId, array $data): void
    {
        $this->repository->saveStatus($processId, $data);
    }

    /** @return array<string, mixed> */
    protected function loadStatusForProcess(string $processId): array
    {
        return $this->repository->getStatus($processId);
    }

    private function countLogErrors(Logger $logger): int
    {
        return count(array_filter(
            $logger->getEntries(),
            static fn(array $entry) => ($entry['level'] ?? '') === 'error',
        ));
    }
}
