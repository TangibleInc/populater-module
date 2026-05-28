<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Seeding\SeedingStatus;
use Tangible\Populater\Seeders\AbstractSeeder;
use Brain\Monkey\Functions;

class AbstractSeedingTest extends \WPTestCase
{
    /** @var AbstractSeeding&\PHPUnit\Framework\MockObject\MockObject */
    private AbstractSeeding $seeding;

    /** @var AbstractSeeder&\PHPUnit\Framework\MockObject\MockObject */
    private AbstractSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('get_option')->justReturn(null);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('wp_generate_uuid4')->justReturn('test-uuid-1234');

        $this->seeder = $this->getMockBuilder(AbstractSeeder::class)
            ->onlyMethods(['buildSeedQueue'])
            ->getMockForAbstractClass();
        $this->seeder->method('getName')->willReturn('Test LMS');
        $this->seeder->method('getSlug')->willReturn('test-lms');
        $this->seeder->method('isActive')->willReturn(true);
        $this->seeder->method('buildSeedQueue')->willReturn([
            ['type' => 'course', 'data' => ['title' => 'Course 1']],
            ['type' => 'user', 'data' => ['email' => 'user@test.com']],
        ]);

        $this->seeding = $this->getMockBuilder(AbstractSeeding::class)
            ->setConstructorArgs([$this->seeder])
            ->onlyMethods(['push_to_queue', 'save', 'dispatch', 'cancelBackgroundQueue'])
            ->getMockForAbstractClass();
    }

    public function test_start_returns_process_id(): void
    {
        $this->seeding->expects($this->exactly(2))->method('push_to_queue')->willReturnSelf();
        $this->seeding->expects($this->once())->method('save')->willReturnSelf();
        $this->seeding->expects($this->once())->method('dispatch');

        $processId = $this->seeding->start([]);

        $this->assertIsString($processId);
        $this->assertNotEmpty($processId);
    }

    public function test_get_status_returns_seeding_status_object(): void
    {
        $status = $this->seeding->getStatus('test-process-id');

        $this->assertInstanceOf(SeedingStatus::class, $status);
    }

    public function test_get_status_returns_pending_when_not_started(): void
    {
        Functions\when('get_option')->justReturn(null);

        $status = $this->seeding->getStatus('nonexistent-id');

        $this->assertSame(SeedingStatus::STATUS_PENDING, $status->getStatus());
    }

    public function test_cancel_sets_status_to_cancelled(): void
    {
        Functions\when('get_option')->justReturn([
            'status' => SeedingStatus::STATUS_RUNNING,
            'plugin' => 'test-lms',
            'total' => 10,
            'processed' => 3,
        ]);
        Functions\expect('update_option')->andReturn(true);

        $this->seeding->expects($this->once())->method('cancelBackgroundQueue');

        $result = $this->seeding->cancelProcess('test-process-id');

        $this->assertTrue($result);
    }

    public function test_cancel_returns_false_for_completed_process(): void
    {
        Functions\when('get_option')->justReturn([
            'status' => SeedingStatus::STATUS_COMPLETED,
            'total' => 10,
            'processed' => 10,
        ]);

        $result = $this->seeding->cancelProcess('test-process-id');

        $this->assertFalse($result);
    }

    public function test_get_logs_returns_array(): void
    {
        Functions\when('get_option')->justReturn([
            ['level' => 'info', 'message' => 'Started', 'timestamp' => time()],
        ]);

        $logs = $this->seeding->getLogs('test-process-id');

        $this->assertIsArray($logs);
    }

    public function test_is_abstract_class(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeding::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_extends_wp_background_process(): void
    {
        $this->assertInstanceOf(\WP_Background_Process::class, $this->seeding);
    }
}
