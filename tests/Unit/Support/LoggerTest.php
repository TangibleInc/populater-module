<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\Logger;
use Brain\Monkey\Functions;

class LoggerTest extends \WPTestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('get_option')->justReturn([]);
        $this->logger = new Logger('test-process-id');
    }

    public function test_log_adds_entry_with_correct_level(): void
    {
        Functions\expect('update_option')->once()->andReturn(true);

        $this->logger->log('Test message', 'info');

        $logs = $this->logger->getEntries();
        $this->assertCount(1, $logs);
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('Test message', $logs[0]['message']);
    }

    public function test_log_stores_timestamp(): void
    {
        Functions\expect('update_option')->once()->andReturn(true);

        $this->logger->log('Test message');

        $logs = $this->logger->getEntries();
        $this->assertArrayHasKey('timestamp', $logs[0]);
        $this->assertIsInt($logs[0]['timestamp']);
    }

    public function test_get_entries_returns_empty_array_when_no_logs(): void
    {
        $this->assertSame([], $this->logger->getEntries());
    }

    public function test_clear_removes_all_entries(): void
    {
        Functions\expect('update_option')->once()->andReturn(true);
        Functions\expect('delete_option')->once()->andReturn(true);

        $this->logger->log('Message 1');
        $this->logger->clear();

        $this->assertSame([], $this->logger->getEntries());
    }

    public function test_log_levels(): void
    {
        Functions\expect('update_option')->times(3)->andReturn(true);

        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');

        $logs = $this->logger->getEntries();
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('warning', $logs[1]['level']);
        $this->assertSame('error', $logs[2]['level']);
    }

    public function test_process_id_is_stored(): void
    {
        $this->assertSame('test-process-id', $this->logger->getProcessId());
    }
}
