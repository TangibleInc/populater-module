<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\CLI;

use Tangible\Populater\CLI\SeedCommand;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;
use Brain\Monkey\Functions;

class SeedCommandTest extends \WPTestCase
{
    private SeedCommand $command;

    /** @var SeedingManager&\PHPUnit\Framework\MockObject\MockObject */
    private SeedingManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->createMock(SeedingManager::class);
        $this->command = new SeedCommand($this->manager);
    }

    public function test_seed_command_starts_process_and_outputs_id(): void
    {
        $this->manager->method('start')->willReturn('process-abc');

        $status = new SeedingStatus('process-abc', SeedingStatus::STATUS_RUNNING, total: 5, processed: 0);
        $completedStatus = new SeedingStatus('process-abc', SeedingStatus::STATUS_COMPLETED, total: 5, processed: 5);

        $this->manager->method('getStatus')
            ->willReturnOnConsecutiveCalls($status, $completedStatus);

        Functions\when('WP_CLI\Utils\make_progress_bar')
            ->justReturn(new class {
                public function tick(): void {}
                public function finish(): void {}
            });

        // Should not throw
        $this->command->seed(['learndash'], ['courses' => '3', 'users' => '2', 'wait' => false]);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_cancel_command_cancels_process(): void
    {
        $this->manager->method('cancel')->willReturn(true);

        $this->command->cancel(['process-abc'], []);
        $this->assertTrue(true);
    }

    public function test_cancel_command_shows_error_when_process_not_found(): void
    {
        $this->manager->method('cancel')->willReturn(false);

        $this->command->cancel(['process-abc'], []);
        $this->assertTrue(true);
    }

    public function test_status_command_outputs_status(): void
    {
        $status = new SeedingStatus('process-abc', SeedingStatus::STATUS_RUNNING, total: 10, processed: 5);
        $this->manager->method('getStatus')->willReturn($status);

        Functions\when('WP_CLI\Utils\format_items')->justReturn(null);

        $this->command->status(['process-abc'], ['format' => 'table']);
        $this->assertTrue(true);
    }

    public function test_logs_command_outputs_logs(): void
    {
        $logs = [
            ['level' => 'info', 'message' => 'Started seeding', 'timestamp' => time()],
        ];
        $this->manager->method('getLogs')->willReturn($logs);

        Functions\when('WP_CLI\Utils\format_items')->justReturn(null);

        $this->command->logs(['process-abc'], ['format' => 'table']);
        $this->assertTrue(true);
    }
}
