<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Integration;

use Tangible\Populater\Database\DatabaseReset;
use Tangible\Populater\LMS\LifterLMS\LifterLmsEnrollmentSetup;
use Tangible\Populater\Tests\Support\IntegrationTestSupport;
use PHPUnit\Framework\TestCase;

/**
 * Base case for tests that require a loaded WordPress environment.
 */
abstract class WordPressIntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_set_current_user(self::adminUserId());

        $reset = new DatabaseReset();
        $this->assertTrue($reset->reset(confirmed: true), 'Failed to reset database before integration test.');

        IntegrationTestSupport::clearBackgroundSeedingState();
        LifterLmsEnrollmentSetup::resetCheckoutState();

        rest_get_server();
        do_action('rest_api_init');
    }

    protected static function adminUserId(): int
    {
        static $adminId = null;

        if ($adminId !== null) {
            return $adminId;
        }

        $admins = get_users([
            'role'   => 'administrator',
            'number' => 1,
            'fields' => 'ID',
        ]);

        $adminId = (int) ($admins[0] ?? 1);

        return $adminId;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $urlParams
     */
    protected function restRequest(string $method, string $route, array $params = [], array $urlParams = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request($method, $route);

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        foreach ($urlParams as $key => $value) {
            $request->set_param($key, $value);
        }

        $response = rest_do_request($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response, (string) wp_json_encode($response));

        return $response;
    }

    /** @return array<string, mixed> */
    protected function restData(\WP_REST_Response $response): array
    {
        $data = $response->get_data();

        return is_array($data) ? $data : [];
    }
}
