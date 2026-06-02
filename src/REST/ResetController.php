<?php

declare(strict_types=1);

namespace Tangible\Populater\REST;

use Tangible\Populater\Database\DatabaseReset;

/**
 * REST controller for database reset.
 *
 * Endpoint (requires manage_options):
 *   POST /tangible-populater/v1/reset   – remove seeded content while preserving site config
 */
class ResetController
{
    private const NAMESPACE = 'tangible-populater/v1';

    public function __construct(private readonly DatabaseReset $dbReset) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/reset', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'resetDatabase'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args'                => [
                'confirmed' => [
                    'required'          => true,
                    'type'              => 'boolean',
                    'description'       => 'Must be true to confirm the destructive operation.',
                ],
            ],
        ]);
    }

    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function resetDatabase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->dbReset->isSafeEnvironment()) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Database reset is disabled on this site.', 'tangible-populater'),
            ]);
        }

        $confirmed = (bool) $request->get_param('confirmed');
        $success   = $this->dbReset->reset(confirmed: $confirmed);

        return rest_ensure_response([
            'success' => $success,
            'message' => $success
                ? __('Seeded content has been removed. Administrator accounts, plugins, and theme were preserved.', 'tangible-populater')
                : __('Reset aborted. Pass confirmed=true to proceed.', 'tangible-populater'),
        ]);
    }
}
