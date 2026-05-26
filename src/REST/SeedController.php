<?php

declare(strict_types=1);

namespace Tangible\Populater\REST;

use Tangible\Populater\Seeding\SeedingManager;

/**
 * REST controller for seeding operations.
 *
 * Endpoints (all require manage_options):
 *   POST   /tangible-populater/v1/seed                         – start a seeding process
 *   GET    /tangible-populater/v1/seed/{id}/status             – get status
 *   GET    /tangible-populater/v1/seed/{id}/logs               – get logs
 *   POST   /tangible-populater/v1/seed/{id}/cancel             – cancel
 *   GET    /tangible-populater/v1/plugins                      – list supported plugins
 */
class SeedController
{
    private const NAMESPACE = 'tangible-populater/v1';

    public function __construct(private readonly SeedingManager $manager) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/seed', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startSeed'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args'                => $this->getSeedArgs(),
        ]);

        register_rest_route(self::NAMESPACE, '/seed/(?P<id>[\w-]+)/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/seed/(?P<id>[\w-]+)/logs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getLogs'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/seed/(?P<id>[\w-]+)/cancel', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'cancelSeed'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/plugins', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'listPlugins'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function startSeed(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $config = [
            'plugin'            => $request->get_param('plugin'),
            'courses'           => (int) ($request->get_param('courses')           ?? 5),
            'lessons_per_course' => (int) ($request->get_param('lessons_per_course') ?? 5),
            'quizzes_per_lesson' => (int) ($request->get_param('quizzes_per_lesson') ?? 1),
            'users'             => (int) ($request->get_param('users')             ?? 10),
        ];

        try {
            $processId = $this->manager->start($config);
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('invalid_plugin', $e->getMessage(), ['status' => 400]);
        } catch (\RuntimeException $e) {
            return new \WP_Error('plugin_inactive', $e->getMessage(), ['status' => 422]);
        }

        return rest_ensure_response([
            'process_id' => $processId,
            'message'    => __('Seeding process started.', 'tangible-populater'),
        ]);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $processId = (string) $request->get_param('id');
        $status    = $this->manager->getStatus($processId);

        return rest_ensure_response($status->toArray());
    }

    public function getLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        $processId = (string) $request->get_param('id');

        return rest_ensure_response([
            'logs' => $this->manager->getLogs($processId),
        ]);
    }

    public function cancelSeed(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $processId = (string) $request->get_param('id');
        $cancelled = $this->manager->cancel($processId);

        if (!$cancelled) {
            return new \WP_Error(
                'cannot_cancel',
                __('Process cannot be cancelled (already completed, failed, or not found).', 'tangible-populater'),
                ['status' => 409]
            );
        }

        return rest_ensure_response(['cancelled' => true]);
    }

    public function listPlugins(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'plugins' => $this->manager->getSupportedPlugins(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    private function getSeedArgs(): array
    {
        return [
            'plugin' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'courses' => [
                'type'    => 'integer',
                'default' => 5,
                'minimum' => 0,
                'maximum' => 500,
            ],
            'lessons_per_course' => [
                'type'    => 'integer',
                'default' => 5,
                'minimum' => 0,
                'maximum' => 100,
            ],
            'quizzes_per_lesson' => [
                'type'    => 'integer',
                'default' => 1,
                'minimum' => 0,
                'maximum' => 50,
            ],
            'users' => [
                'type'    => 'integer',
                'default' => 10,
                'minimum' => 0,
                'maximum' => 1000,
            ],
        ];
    }
}
