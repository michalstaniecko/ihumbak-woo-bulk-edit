<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for GET /fields.
 */
final class FieldsController extends RestController
{
    protected $rest_base = 'fields';

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
        private readonly CapabilityChecker $capabilityChecker,
    ) {}

    public function register_routes(): void
    {
        register_rest_route($this->getNamespace(), '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
            ],
        ]);
    }

    public function get_items($request): WP_REST_Response
    {
        return $this->success($this->fieldRegistry->toArray());
    }
}
