<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Support\Logger;

/**
 * Centralizes option keys for seeding process state.
 */
class ProcessRepository
{
    public const PREFIX_STATUS     = 'tangible_populater_status_';
    public const PREFIX_LOGS       = 'tangible_populater_logs_';
    public const PREFIX_IDS        = 'tangible_populater_ids_';
    public const ACTIVE_PROCESS    = 'tangible_populater_active_process';

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

    /** @return array<string, mixed> */
    public function getIdMap(string $processId): array
    {
        $stored = get_option(self::PREFIX_IDS . $processId, null);

        if (!is_array($stored)) {
            return ['courses' => [], 'lessons' => []];
        }

        $map = [
            'courses' => is_array($stored['courses'] ?? null) ? $stored['courses'] : [],
            'lessons' => is_array($stored['lessons'] ?? null) ? $stored['lessons'] : [],
        ];

        foreach (['topics', 'sections', 'modules', 'section_lessons', 'groups', 'group_admins'] as $entity) {
            if (isset($stored[$entity]) && is_array($stored[$entity])) {
                $map[$entity] = $stored[$entity];
            }
        }

        return $map;
    }

    /** @param array<string, mixed> $map */
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
        $this->clearActiveProcessIfMatches($processId);
    }

    public function setActiveProcess(string $processId): void
    {
        update_option(self::ACTIVE_PROCESS, $processId, false);
    }

    public function getStoredActiveProcessId(): ?string
    {
        $processId = get_option(self::ACTIVE_PROCESS, null);

        return is_string($processId) && $processId !== '' ? $processId : null;
    }

    public function findActiveProcessId(): ?string
    {
        $stored = $this->getStoredActiveProcessId();

        if ($stored !== null && $this->isActiveStatus($this->getStatus($stored))) {
            return $stored;
        }

        global $wpdb;

        $like = $wpdb->esc_like(self::PREFIX_STATUS) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            ),
            ARRAY_A
        );

        $activeId       = null;
        $latestProgress = -1;

        foreach ($rows as $row) {
            $data = maybe_unserialize($row['option_value']);

            if (!is_array($data) || !$this->isActiveStatus($data)) {
                continue;
            }

            $processId = substr((string) $row['option_name'], strlen(self::PREFIX_STATUS));
            $progress  = (int) ($data['processed'] ?? 0);

            if ($progress >= $latestProgress) {
                $latestProgress = $progress;
                $activeId       = $processId;
            }
        }

        if ($activeId !== null) {
            $this->setActiveProcess($activeId);

            return $activeId;
        }

        if ($stored !== null) {
            $this->clearActiveProcess();
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private function isActiveStatus(array $data): bool
    {
        return in_array($data['status'] ?? '', [
            SeedingStatus::STATUS_PENDING,
            SeedingStatus::STATUS_RUNNING,
        ], true);
    }

    public function clearActiveProcess(): void
    {
        delete_option(self::ACTIVE_PROCESS);
    }

    public function clearActiveProcessIfMatches(string $processId): void
    {
        if ($this->getStoredActiveProcessId() === $processId) {
            $this->clearActiveProcess();
        }
    }
}
