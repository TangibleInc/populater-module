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
}
