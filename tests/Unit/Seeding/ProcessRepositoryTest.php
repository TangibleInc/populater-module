<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\ProcessRepository;
use Brain\Monkey\Functions;

class ProcessRepositoryTest extends \WPTestCase
{
    private ProcessRepository $repository;

    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProcessRepository();
        $this->options    = [];

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }

        Functions\when('maybe_unserialize')->alias(static fn(mixed $value) => @unserialize((string) $value) ?: $value);
        Functions\when('get_option')->alias(function (string $key) {
            return $this->options[$key] ?? null;
        });
        Functions\when('update_option')->alias(function (string $key, mixed $value): bool {
            $this->options[$key] = $value;

            return true;
        });
        Functions\when('delete_option')->alias(function (string $key): bool {
            unset($this->options[$key]);

            return true;
        });
    }

    public function test_save_and_get_status(): void
    {
        $this->repository->saveStatus('proc-1', ['status' => 'running', 'plugin' => 'learndash']);

        $this->assertSame('running', $this->repository->getStatus('proc-1')['status']);
        $this->assertSame('learndash', $this->repository->getStatus('proc-1')['plugin']);
    }

    public function test_delete_process_clears_status_and_id_map(): void
    {
        $this->repository->saveStatus('proc-1', ['status' => 'completed']);
        $this->repository->saveIdMap('proc-1', ['courses' => [], 'lessons' => []]);
        $this->repository->appendLog('proc-1', 'done');

        $this->repository->deleteProcess('proc-1');

        $this->assertSame([], $this->repository->getStatus('proc-1'));
        $this->assertSame(['courses' => [], 'lessons' => []], $this->repository->getIdMap('proc-1'));
    }

    public function test_active_process_tracking(): void
    {
        $this->repository->setActiveProcess('proc-1');

        $this->assertSame('proc-1', $this->repository->getStoredActiveProcessId());

        $this->repository->clearActiveProcessIfMatches('proc-2');
        $this->assertSame('proc-1', $this->repository->getStoredActiveProcessId());

        $this->repository->clearActiveProcessIfMatches('proc-1');
        $this->assertNull($this->repository->getStoredActiveProcessId());
    }

    public function test_find_active_process_uses_stored_pointer_when_running(): void
    {
        $this->repository->saveStatus('proc-1', ['status' => 'running', 'processed' => 5]);
        $this->repository->setActiveProcess('proc-1');

        $this->assertSame('proc-1', $this->repository->findActiveProcessId());
    }

    public function test_find_active_process_scans_status_options_when_pointer_missing(): void
    {
        global $wpdb;

        $this->repository->saveStatus('proc-1', ['status' => 'running', 'processed' => 12]);

        $wpdb = \Mockery::mock('wpdb');
        $wpdb->options = 'wp_options';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(static fn(string $text) => $text);
        $wpdb->shouldReceive('prepare')->andReturnUsing(static fn(string $query) => $query);
        $wpdb->shouldReceive('get_results')->andReturn([
            [
                'option_name'  => ProcessRepository::PREFIX_STATUS . 'proc-1',
                'option_value' => serialize(['status' => 'running', 'processed' => 12]),
            ],
        ]);

        $this->assertSame('proc-1', $this->repository->findActiveProcessId());
        $this->assertSame('proc-1', $this->repository->getStoredActiveProcessId());
    }

    public function test_delete_process_clears_active_process_pointer(): void
    {
        $this->repository->setActiveProcess('proc-1');
        $this->repository->deleteProcess('proc-1');

        $this->assertNull($this->repository->getStoredActiveProcessId());
    }
}
