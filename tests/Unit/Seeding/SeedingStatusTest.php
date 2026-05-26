<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\SeedingStatus;

class SeedingStatusTest extends \WPTestCase
{
    public function test_can_be_created_with_defaults(): void
    {
        $status = new SeedingStatus('process-id');

        $this->assertSame('process-id', $status->getId());
        $this->assertSame(SeedingStatus::STATUS_PENDING, $status->getStatus());
        $this->assertSame(0, $status->getTotal());
        $this->assertSame(0, $status->getProcessed());
        $this->assertSame(0.0, $status->getProgress());
    }

    public function test_progress_calculation_is_correct(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_RUNNING, total: 10, processed: 5);

        $this->assertSame(50.0, $status->getProgress());
    }

    public function test_progress_is_zero_when_total_is_zero(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_RUNNING, total: 0, processed: 0);

        $this->assertSame(0.0, $status->getProgress());
    }

    public function test_progress_is_100_when_all_processed(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_COMPLETED, total: 5, processed: 5);

        $this->assertSame(100.0, $status->getProgress());
    }

    public function test_is_running_returns_true_for_running_status(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_RUNNING);

        $this->assertTrue($status->isRunning());
        $this->assertFalse($status->isCompleted());
        $this->assertFalse($status->isCancelled());
        $this->assertFalse($status->isFailed());
    }

    public function test_is_completed_returns_true_for_completed_status(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_COMPLETED);

        $this->assertFalse($status->isRunning());
        $this->assertTrue($status->isCompleted());
    }

    public function test_is_cancelled_returns_true_for_cancelled_status(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_CANCELLED);

        $this->assertTrue($status->isCancelled());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_FAILED);

        $this->assertTrue($status->isFailed());
    }

    public function test_can_be_cancelled_only_when_pending_or_running(): void
    {
        $pending = new SeedingStatus('id', SeedingStatus::STATUS_PENDING);
        $running = new SeedingStatus('id', SeedingStatus::STATUS_RUNNING);
        $completed = new SeedingStatus('id', SeedingStatus::STATUS_COMPLETED);
        $failed = new SeedingStatus('id', SeedingStatus::STATUS_FAILED);

        $this->assertTrue($pending->canBeCancelled());
        $this->assertTrue($running->canBeCancelled());
        $this->assertFalse($completed->canBeCancelled());
        $this->assertFalse($failed->canBeCancelled());
    }

    public function test_to_array_returns_all_fields(): void
    {
        $status = new SeedingStatus('id', SeedingStatus::STATUS_RUNNING, total: 10, processed: 4);
        $arr = $status->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('status', $arr);
        $this->assertArrayHasKey('total', $arr);
        $this->assertArrayHasKey('processed', $arr);
        $this->assertArrayHasKey('progress', $arr);
    }

    public function test_from_array_restores_status(): void
    {
        $data = [
            'status' => SeedingStatus::STATUS_RUNNING,
            'total' => 10,
            'processed' => 3,
            'error' => null,
        ];

        $status = SeedingStatus::fromArray('id', $data);

        $this->assertSame(SeedingStatus::STATUS_RUNNING, $status->getStatus());
        $this->assertSame(10, $status->getTotal());
        $this->assertSame(3, $status->getProcessed());
    }
}
