<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\REST;

use Tangible\Populater\REST\ResetController;
use Tangible\Populater\Database\DatabaseReset;
use Brain\Monkey\Functions;

class ResetControllerTest extends \WPTestCase
{
    private ResetController $controller;

    /** @var DatabaseReset&\PHPUnit\Framework\MockObject\MockObject */
    private DatabaseReset $dbReset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbReset = $this->createMock(DatabaseReset::class);
        $this->dbReset->method('isSafeEnvironment')->willReturn(true);
        $this->controller = new ResetController($this->dbReset);
    }

    public function test_register_routes_defines_reset_endpoint(): void
    {
        Functions\expect('register_rest_route')
            ->once()
            ->with('tangible-populater/v1', '/reset', \Mockery::any());

        $this->controller->registerRoutes();
        $this->addToAssertionCount(1);
    }

    public function test_permission_callback_requires_admin(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->assertFalse($this->controller->checkAdminPermission());
    }

    public function test_reset_database_returns_success_when_confirmed(): void
    {
        $this->dbReset->method('reset')->willReturn(true);

        $request = new \WP_REST_Request(['confirmed' => true]);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->resetDatabase($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
    }

    public function test_reset_database_returns_error_when_not_confirmed(): void
    {
        $request = new \WP_REST_Request(['confirmed' => false]);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $this->dbReset->method('reset')->willReturn(false);

        $response = $this->controller->resetDatabase($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function test_reset_database_returns_error_when_not_safe_environment(): void
    {
        $this->dbReset->method('isSafeEnvironment')->willReturn(false);

        $request = new \WP_REST_Request(['confirmed' => true]);

        Functions\expect('rest_ensure_response')
            ->once()
            ->andReturnUsing(static fn (mixed $data): \WP_REST_Response => new \WP_REST_Response($data));

        $response = $this->controller->resetDatabase($request);
        $data     = $response instanceof \WP_REST_Response ? $response->get_data() : [];

        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }
}
