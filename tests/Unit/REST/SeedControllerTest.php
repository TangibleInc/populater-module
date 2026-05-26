<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\REST;

use Tangible\Populater\REST\SeedController;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Seeding\SeedingStatus;
use Brain\Monkey\Functions;

class SeedControllerTest extends \WPTestCase
{
    private SeedController $controller;

    /** @var SeedingManager&\PHPUnit\Framework\MockObject\MockObject */
    private SeedingManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->createMock(SeedingManager::class);
        $this->controller = new SeedController($this->manager);
    }

    public function test_register_routes_defines_seed_endpoint(): void
    {
        Functions\expect('register_rest_route')
            ->atLeast()
            ->once()
            ->with('tangible-populater/v1', '/seed', \Mockery::any());

        $this->controller->registerRoutes();
        $this->addToAssertionCount(1);
    }

    public function test_register_routes_defines_cancel_endpoint(): void
    {
        Functions\expect('register_rest_route')
            ->atLeast()
            ->once()
            ->with('tangible-populater/v1', '/seed/(?P<id>[\\w-]+)/cancel', \Mockery::any());

        $this->controller->registerRoutes();
        $this->addToAssertionCount(1);
    }

    public function test_register_routes_defines_status_endpoint(): void
    {
        Functions\expect('register_rest_route')
            ->atLeast()
            ->once()
            ->with('tangible-populater/v1', '/seed/(?P<id>[\\w-]+)/status', \Mockery::any());

        $this->controller->registerRoutes();
        $this->addToAssertionCount(1);
    }

    public function test_register_routes_defines_logs_endpoint(): void
    {
        Functions\expect('register_rest_route')
            ->atLeast()
            ->once()
            ->with('tangible-populater/v1', '/seed/(?P<id>[\\w-]+)/logs', \Mockery::any());

        $this->controller->registerRoutes();
        $this->addToAssertionCount(1);
    }

    public function test_permission_callback_requires_admin(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->assertFalse($this->controller->checkAdminPermission());
    }

    public function test_permission_callback_allows_admin(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $this->assertTrue($this->controller->checkAdminPermission());
    }

    public function test_start_seed_returns_process_id(): void
    {
        $this->manager
            ->method('start')
            ->willReturn('process-123');

        $request = $this->createMockRequest(['plugin' => 'learndash', 'courses' => 5]);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->startSeed($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('process_id', $data);
        $this->assertSame('process-123', $data['process_id']);
    }

    public function test_get_status_returns_seeding_status(): void
    {
        $status = new SeedingStatus('process-123', SeedingStatus::STATUS_RUNNING, total: 10, processed: 3);
        $this->manager->method('getStatus')->willReturn($status);

        $request = $this->createMockRequest([], ['id' => 'process-123']);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->getStatus($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('progress', $data);
    }

    public function test_cancel_seed_returns_success(): void
    {
        $this->manager->method('cancel')->willReturn(true);

        $request = $this->createMockRequest([], ['id' => 'process-123']);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->cancelSeed($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('cancelled', $data);
        $this->assertTrue($data['cancelled']);
    }

    public function test_get_logs_returns_log_array(): void
    {
        $logs = [
            ['level' => 'info', 'message' => 'Started', 'timestamp' => time()],
        ];
        $this->manager->method('getLogs')->willReturn($logs);

        $request = $this->createMockRequest([], ['id' => 'process-123']);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->getLogs($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('logs', $data);
        $this->assertIsArray($data['logs']);
    }

    /**
     * Create a mock WP_REST_Request.
     */
    private function createMockRequest(array $params = [], array $urlParams = []): object
    {
        return new \WP_REST_Request($params, $urlParams);
    }
}
