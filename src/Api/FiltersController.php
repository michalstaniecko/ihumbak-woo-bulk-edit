<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Persistence\SavedFiltersRepository;
use IhumbakWooBulkEdit\Query\FilterDefinitionValidator;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for saved filter presets.
 *
 * Routes:
 *   GET    /filters           — list filters visible to the current user
 *   POST   /filters           — create a new filter
 *   PUT    /filters/{id}      — update an existing filter
 *   DELETE /filters/{id}      — delete a filter
 *
 * @license GPL-2.0-or-later
 */
final class FiltersController extends RestController
{
    protected $rest_base = 'filters';

    public function __construct(
        private readonly SavedFiltersRepository $repository,
        private readonly CapabilityChecker $capabilityChecker,
        private readonly FilterDefinitionValidator $definitionValidator = new FilterDefinitionValidator(),
    ) {}

    public function register_routes(): void
    {
        // Collection routes.
        register_rest_route($this->getNamespace(), '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
                'args'                => $this->getCreateArgs(),
            ],
        ]);

        // Item routes.
        register_rest_route($this->getNamespace(), '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'permissionModify'],
                'args'                => $this->getUpdateArgs(),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this, 'permissionModify'],
            ],
        ]);
    }

    /**
     * GET /filters — list filters visible to the current user.
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        $items = $this->repository->listForUser($userId);

        return $this->success(['items' => $items]);
    }

    /**
     * POST /filters — create a new saved filter.
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $name = sanitize_text_field((string) ($request->get_param('name') ?? ''));

        if ($name === '') {
            return $this->error(
                'wbm_filter_invalid_name',
                __('Filter name cannot be empty.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $definitionParam = $request->get_param('definition');
        if (! is_array($definitionParam)) {
            return $this->error(
                'wbm_filter_invalid_definition',
                __('Filter definition must be an object.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $validationError = $this->definitionValidator->validate($definitionParam);
        if ($validationError !== null) {
            $errData = $validationError->get_error_data() ?? [];
            $status  = is_array($errData) && isset($errData['status']) ? (int) $errData['status'] : 400;
            return $this->error($validationError->get_error_code(), $validationError->get_error_message(), $status);
        }

        $isShared = (bool) ($request->get_param('is_shared') ?? false);

        // Sharing requires manage_woocommerce.
        if ($isShared && ! $this->capabilityChecker->canManageSharedFilters()) {
            return $this->error(
                'wbm_cannot_share_filters',
                __('You do not have permission to share filters.', 'ihumbak-woo-bulk-edit'),
                403
            );
        }

        $userId = $isShared ? 0 : (int) get_current_user_id();

        $result = $this->repository->create($userId, $name, $definitionParam, $isShared);

        if (is_wp_error($result)) {
            return $this->wpErrorToResponse($result);
        }

        $row = $this->repository->find($result);

        return $this->success($row, 201);
    }

    /**
     * PUT /filters/{id} — update a saved filter.
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        $name = $request->get_param('name');
        if ($name !== null) {
            $name = sanitize_text_field((string) $name);
            if ($name === '') {
                return $this->error(
                    'wbm_filter_invalid_name',
                    __('Filter name cannot be empty.', 'ihumbak-woo-bulk-edit'),
                    400
                );
            }
        }

        $definitionParam = $request->get_param('definition');
        if ($definitionParam !== null && ! is_array($definitionParam)) {
            return $this->error(
                'wbm_filter_invalid_definition',
                __('Filter definition must be an object.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        if (is_array($definitionParam)) {
            $validationError = $this->definitionValidator->validate($definitionParam);
            if ($validationError !== null) {
                $errData = $validationError->get_error_data() ?? [];
                $status  = is_array($errData) && isset($errData['status']) ? (int) $errData['status'] : 400;
                return $this->error($validationError->get_error_code(), $validationError->get_error_message(), $status);
            }
        }

        $isShared = $request->get_param('is_shared');
        if ($isShared !== null) {
            $isShared = (bool) $isShared;
            if ($isShared && ! $this->capabilityChecker->canManageSharedFilters()) {
                return $this->error(
                    'wbm_cannot_share_filters',
                    __('You do not have permission to share filters.', 'ihumbak-woo-bulk-edit'),
                    403
                );
            }
        }

        $result = $this->repository->update(
            $id,
            $name,
            $definitionParam,
            $isShared,
        );

        if (is_wp_error($result)) {
            return $this->wpErrorToResponse($result);
        }

        return $this->success($this->repository->find($id));
    }

    /**
     * DELETE /filters/{id} — delete a saved filter.
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        $deleted = $this->repository->delete($id);

        if (! $deleted) {
            return $this->error(
                'wbm_filter_not_found',
                __('Filter not found.', 'ihumbak-woo-bulk-edit'),
                404
            );
        }

        return $this->success(['deleted' => true, 'id' => $id]);
    }

    /**
     * Per-row permission check for PUT and DELETE.
     *
     * Returns 404 WP_Error if the row does not exist, or 403 WP_Error if the
     * current user is not allowed to modify it.
     */
    public function permissionModify(WP_REST_Request $request): bool|WP_Error
    {
        if (! $this->capabilityChecker->canRead()) {
            return false;
        }

        $id = (int) $request->get_param('id');
        $row = $this->repository->find($id);

        if ($row === null) {
            return new WP_Error(
                'wbm_filter_not_found',
                __('Filter not found.', 'ihumbak-woo-bulk-edit'),
                ['status' => 404]
            );
        }

        // Shared rows (user_id = 0) require manage_woocommerce.
        if ($row['user_id'] === 0) {
            if (! $this->capabilityChecker->canManageSharedFilters()) {
                return new WP_Error(
                    'wbm_cannot_share_filters',
                    __('You do not have permission to modify shared filters.', 'ihumbak-woo-bulk-edit'),
                    ['status' => 403]
                );
            }
            return true;
        }

        // Private rows: only the owner may modify them.
        if ($row['user_id'] !== (int) get_current_user_id()) {
            return new WP_Error(
                'wbm_forbidden',
                __('You do not have permission to modify this filter.', 'ihumbak-woo-bulk-edit'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Convert a WP_Error from the repository into an HTTP error response.
     */
    private function wpErrorToResponse(WP_Error $error): WP_Error
    {
        $data = $error->get_error_data() ?? [];
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;

        return $this->error($error->get_error_code(), $error->get_error_message(), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCreateArgs(): array
    {
        return [
            'name' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'minLength'         => 1,
                'maxLength'         => 191,
            ],
            'definition' => [
                'type'     => 'object',
                'required' => true,
            ],
            'is_shared' => [
                'type'    => 'boolean',
                'default' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdateArgs(): array
    {
        return [
            'name' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
                'minLength'         => 1,
                'maxLength'         => 191,
            ],
            'definition' => [
                'type'     => 'object',
                'required' => false,
            ],
            'is_shared' => [
                'type'     => 'boolean',
                'required' => false,
            ],
        ];
    }
}
