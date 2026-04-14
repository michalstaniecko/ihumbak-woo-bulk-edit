<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Persistence\UserPreferencesRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for per-user plugin preferences.
 *
 * Routes:
 *   GET    /preferences/column-visibility — get column visibility state
 *   PUT    /preferences/column-visibility — update hidden columns
 *   DELETE /preferences/column-visibility — reset to defaults
 *
 * All routes require edit_products capability.
 *
 * @license GPL-2.0-or-later
 */
final class UserPreferencesController extends RestController
{
    protected $rest_base = 'preferences/column-visibility';

    public function __construct(
        private readonly UserPreferencesRepository $repository,
        private readonly CapabilityChecker $capabilityChecker,
    ) {}

    public function register_routes(): void
    {
        register_rest_route($this->getNamespace(), '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this->capabilityChecker, 'permissionWrite'],
                'args'                => $this->getUpdateArgs(),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this->capabilityChecker, 'permissionWrite'],
            ],
        ]);
    }

    /**
     * GET /preferences/column-visibility
     *
     * Returns the current user's column visibility preferences.
     */
    public function get(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        $data   = $this->repository->getColumnVisibility($userId);

        return $this->success($data);
    }

    /**
     * PUT /preferences/column-visibility
     *
     * Updates the hidden columns list for the current user.
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $hidden = $request->get_param('hidden');

        if (! is_array($hidden)) {
            return $this->error(
                'wbm_preferences_invalid_hidden',
                __('The hidden parameter must be an array.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        // Sanitize each element and filter pinned columns at the controller layer as well.
        $sanitized = array_values(
            array_filter(
                array_map('sanitize_key', $hidden),
                fn(string $key): bool => $key !== '' && ! in_array($key, ['select', 'id'], true)
            )
        );

        $userId = (int) get_current_user_id();
        $this->repository->updateColumnVisibility($userId, $sanitized);

        return $this->success($this->repository->getColumnVisibility($userId));
    }

    /**
     * DELETE /preferences/column-visibility
     *
     * Resets the current user's column visibility to defaults.
     */
    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        $this->repository->resetColumnVisibility($userId);

        return $this->success($this->repository->getColumnVisibility($userId));
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdateArgs(): array
    {
        return [
            'hidden' => [
                'type'     => 'array',
                'required' => true,
                'items'    => [
                    'type' => 'string',
                ],
            ],
        ];
    }
}
