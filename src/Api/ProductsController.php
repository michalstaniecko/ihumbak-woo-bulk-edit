<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Query\QueryBuilder;
use IhumbakWooBulkEdit\Query\FilterParser;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Persistence\BatchSaver;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use IhumbakWooBulkEdit\Security\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for product query and batch save.
 */
final class ProductsController extends RestController
{
    protected $rest_base = 'products';

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
        private readonly CapabilityChecker $capabilityChecker,
        private readonly RateLimiter $rateLimiter,
        private readonly BatchSaver $batchSaver,
    ) {}

    public function register_routes(): void
    {
        register_rest_route($this->getNamespace(), '/' . $this->rest_base . '/query', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'query'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
                'args'                => $this->getQueryArgs(),
            ],
        ]);

        register_rest_route($this->getNamespace(), '/' . $this->rest_base . '/batch', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'batch_save'],
                'permission_callback' => [$this->capabilityChecker, 'permissionWrite'],
                'args'                => $this->getBatchSaveArgs(),
            ],
        ]);

        register_rest_route($this->getNamespace(), '/' . $this->rest_base . '/batch', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'batch_delete'],
                'permission_callback' => [$this->capabilityChecker, 'permissionDelete'],
            ],
        ]);
    }

    /**
     * POST /products/query — filter, sort, paginate products.
     */
    public function query(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $filters  = $request->get_param('filters') ?? [];
        $sort     = $request->get_param('sort') ?? ['field' => 'name', 'order' => 'asc'];
        $page     = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage  = min(500, max(10, (int) ($request->get_param('per_page') ?? 50)));

        $parser = new FilterParser($this->fieldRegistry);
        $builder = new QueryBuilder();

        $parseResult = $parser->apply($builder, $filters);

        if ($parseResult instanceof WP_Error) {
            return $parseResult;
        }

        $sortField = $this->fieldRegistry->get($sort['field'] ?? 'name');

        if ($sortField !== null && $sortField->isSortable()) {
            $builder->orderBy($sort['field'], $sort['order'] ?? 'asc');
        }

        $builder->paginate($page, $perPage);

        $total = $builder->getTotal();
        $products = $builder->getResults();

        return $this->success([
            'items'    => $products,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * PUT /products/batch — save changes.
     */
    public function batch_save(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimitCheck = $this->rateLimiter->check('batch');

        if ($rateLimitCheck instanceof WP_Error) {
            return $rateLimitCheck;
        }

        $changes = $request->get_param('changes');

        if (! is_array($changes) || empty($changes)) {
            return $this->error(
                'wbm_invalid_changes',
                __('No changes provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        // Validate each change item structure.
        foreach ($changes as $index => $change) {
            if (
                ! is_array($change)
                || ! isset($change['id'], $change['field'], $change['post_modified'])
                || ! is_numeric($change['id'])
                || ! is_string($change['field'])
                || ! is_string($change['post_modified'])
                || ! array_key_exists('value', $change)
            ) {
                return $this->error(
                    'wbm_invalid_change_item',
                    sprintf(
                        /* translators: %d: index of the invalid change */
                        __('Invalid change item at index %d. Required: id (int), field (string), value, post_modified (string).', 'ihumbak-woo-bulk-edit'),
                        $index
                    ),
                    400
                );
            }
        }

        $batchSize = min(500, max(10, (int) ($request->get_param('batch_size') ?? 50)));

        $result = $this->batchSaver->process($changes, $batchSize);

        return $this->success($result);
    }

    /**
     * DELETE /products/batch — bulk delete.
     */
    public function batch_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimitCheck = $this->rateLimiter->check('batch');

        if ($rateLimitCheck instanceof WP_Error) {
            return $rateLimitCheck;
        }

        $ids = $request->get_param('ids');

        if (! is_array($ids) || empty($ids)) {
            return $this->error(
                'wbm_invalid_ids',
                __('No product IDs provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        // BulkDelete will be implemented in Issue #23.
        return $this->success([
            'message' => 'Batch delete endpoint ready — BulkDelete pending implementation.',
            'count'   => count($ids),
        ]);
    }

    private function getBatchSaveArgs(): array
    {
        return [
            'changes' => [
                'type'     => 'array',
                'required' => true,
                'items'    => [
                    'type' => 'object',
                ],
            ],
            'batch_size' => [
                'type'    => 'integer',
                'default' => 50,
                'minimum' => 10,
                'maximum' => 500,
            ],
        ];
    }

    private function getQueryArgs(): array
    {
        return [
            'filters' => [
                'type'    => 'array',
                'default' => [],
            ],
            'sort' => [
                'type'    => 'object',
                'default' => ['field' => 'name', 'order' => 'asc'],
            ],
            'page' => [
                'type'    => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'type'    => 'integer',
                'default' => 50,
                'minimum' => 10,
                'maximum' => 500,
            ],
        ];
    }
}
