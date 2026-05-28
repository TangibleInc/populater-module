<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Support\Logger;

/**
 * Centralizes option keys for seeding process state.
 */
class ProcessRepository
{
    public const PREFIX_STATUS = 'tangible_populater_status_';
    public const PREFIX_LOGS   = 'tangible_populater_logs_';
    public const PREFIX_IDS    = 'tangible_populater_ids_';

    /** @return array<string, mixed> */
    public function getStatus(string $processId): array
    {
        $data = get_option(self::PREFIX_STATUS . $processId, null);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    public function saveStatus(string $processId, array $data): void
    {
        update_option(self::PREFIX_STATUS . $processId, $data, false);
    }

    /**
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getLogs(string $processId): array
    {
        return (new Logger($processId))->getEntries();
    }

    public function appendLog(string $processId, string $message, string $level = 'info'): void
    {
        (new Logger($processId))->log($message, $level);
    }

    /** @return array{courses: array<int, int>, lessons: array<int, array<int, int>>} */
    public function getIdMap(string $processId): array
    {
        $stored = get_option(self::PREFIX_IDS . $processId, null);

        if (!is_array($stored)) {
            return ['courses' => [], 'lessons' => []];
        }

        return [
            'courses' => is_array($stored['courses'] ?? null) ? $stored['courses'] : [],
            'lessons' => is_array($stored['lessons'] ?? null) ? $stored['lessons'] : [],
        ];
    }

    /** @param array{courses: array<int, int>, lessons: array<int, array<int, int>>} $map */
    public function saveIdMap(string $processId, array $map): void
    {
        update_option(self::PREFIX_IDS . $processId, $map, false);
    }

    public function deleteIdMap(string $processId): void
    {
        delete_option(self::PREFIX_IDS . $processId);
    }

    public function deleteProcess(string $processId): void
    {
        delete_option(self::PREFIX_STATUS . $processId);
        delete_option(self::PREFIX_IDS . $processId);
        (new Logger($processId))->clear();
    }
}
