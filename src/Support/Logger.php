<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Persists log entries for a seeding process in a WP option.
 */
class Logger
{
    private const OPTION_PREFIX = 'tangible_populater_logs_';
    private const MAX_ENTRIES   = 500;

    /** @var list<array{level: string, message: string, timestamp: int}> */
    private array $entries = [];

    public function __construct(private readonly string $processId)
    {
        $stored = get_option(self::OPTION_PREFIX . $processId, []);
        $this->entries = is_array($stored) ? $stored : [];
    }

    public function getProcessId(): string
    {
        return $this->processId;
    }

    public function log(string $message, string $level = 'info'): void
    {
        $this->entries[] = [
            'level'     => $level,
            'message'   => $message,
            'timestamp' => time(),
        ];

        // Keep the log from growing unbounded in the option.
        if (count($this->entries) > self::MAX_ENTRIES) {
            $this->entries = array_slice($this->entries, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION_PREFIX . $this->processId, $this->entries, false);
    }

    public function info(string $message): void
    {
        $this->log($message, 'info');
    }

    public function warning(string $message): void
    {
        $this->log($message, 'warning');
    }

    public function error(string $message): void
    {
        $this->log($message, 'error');
    }

    /**
     * @return list<array{level: string, message: string, timestamp: int}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
        delete_option(self::OPTION_PREFIX . $this->processId);
    }
}
