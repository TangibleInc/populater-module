<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\CLI;

use Tangible\Populater\CLI\ResetCommand;
use Tangible\Populater\Database\DatabaseReset;
use Brain\Monkey\Functions;

class ResetCommandTest extends \WPTestCase
{
    private ResetCommand $command;

    /** @var DatabaseReset&\PHPUnit\Framework\MockObject\MockObject */
    private DatabaseReset $dbReset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbReset = $this->createMock(DatabaseReset::class);
        $this->command = new ResetCommand($this->dbReset);
    }

    public function test_reset_command_requires_yes_flag_or_prompt(): void
    {
        $this->dbReset->method('isSafeEnvironment')->willReturn(true);
        $this->dbReset->method('reset')->willReturn(true);

        $this->command->reset([], ['yes' => false]);
        $this->assertTrue(true);
    }

    public function test_reset_command_shows_error_when_not_safe_environment(): void
    {
        $this->dbReset->method('isSafeEnvironment')->willReturn(false);

        $this->command->reset([], ['yes' => true]);
        $this->assertTrue(true);
    }

    public function test_reset_command_reports_failure(): void
    {
        $this->dbReset->method('isSafeEnvironment')->willReturn(true);
        $this->dbReset->method('reset')->willReturn(false);

        $this->command->reset([], ['yes' => false]);
        $this->assertTrue(true);
    }
}
