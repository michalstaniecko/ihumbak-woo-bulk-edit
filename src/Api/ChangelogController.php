<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for the change-log audit trail.
 *
 * @license GPL-2.0-or-later
 */
final class ChangelogController extends RestController
{
    protected $rest_base = 'changelog';

    public function __construct(
        private readonly ChangeLogRepository $repository,
        private readonly FieldRegistry $fieldRegistry,
        private readonly CapabilityChecker $capabilityChecker,
    ) {}

    public function register_routes(): void
    {
        register_rest_route($this->getNamespace(), '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'query'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
                'args'                => $this->getQueryArgs(),
            ],
        ]);
    }

    /**
     * GET /changelog — list audit log entries.
     */
    public function query(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $args = [
            'page'     => max(1, (int) ($request->get_param('page') ?? 1)),
            'per_page' => min(500, max(1, (int) ($request->get_param('per_page') ?? 50))),
        ];

        $productId = $request->get_param('product_id');
        if ($productId !== null && $productId !== '') {
            $args['product_id'] = (int) $productId;
        }

        $userId = $request->get_param('user_id');
        if ($userId !== null && $userId !== '') {
            $args['user_id'] = (int) $userId;
        }

        $field = $request->get_param('field');
        if (is_string($field) && $field !== '') {
            if ($this->fieldRegistry->get($field) === null) {
                return $this->error(
                    'wbm_invalid_field',
                    sprintf(
                        /* translators: %s: field key */
                        __('Unknown field: %s', 'ihumbak-woo-bulk-edit'),
                        $field
                    ),
                    400
                );
            }
            $args['field'] = $field;
        }

        $dateFrom = $request->get_param('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            $args['date_from'] = $dateFrom;
        }

        $dateTo = $request->get_param('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            $args['date_to'] = $dateTo;
        }

        return $this->success($this->repository->query($args));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getQueryArgs(): array
    {
        return [
            'page' => [
                'type'    => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'type'    => 'integer',
                'default' => 50,
                'minimum' => 1,
                'maximum' => 500,
            ],
            'product_id' => [
                'type'     => 'integer',
                'required' => false,
            ],
            'user_id' => [
                'type'     => 'integer',
                'required' => false,
            ],
            'field' => [
                'type'     => 'string',
                'required' => false,
            ],
            'date_from' => [
                'type'        => 'string',
                'required'    => false,
                'description' => 'YYYY-MM-DD or YYYY-MM-DD HH:MM:SS',
            ],
            'date_to' => [
                'type'        => 'string',
                'required'    => false,
                'description' => 'YYYY-MM-DD or YYYY-MM-DD HH:MM:SS',
            ],
        ];
    }
}
