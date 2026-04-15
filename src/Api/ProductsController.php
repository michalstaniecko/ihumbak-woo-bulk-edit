<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Query\QueryBuilder;
use IhumbakWooBulkEdit\Query\FilterParser;
use IhumbakWooBulkEdit\Query\VariationsRepository;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Operations\BulkDelete;
use IhumbakWooBulkEdit\Operations\BulkDuplicate;
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
        private readonly BulkDelete $bulkDelete,
        private readonly BulkDuplicate $bulkDuplicate,
        private readonly VariationsRepository $variationsRepository = new VariationsRepository(),
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
                'args'                => $this->getBatchDeleteArgs(),
            ],
        ]);

        register_rest_route($this->getNamespace(), '/' . $this->rest_base . '/duplicate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'batch_duplicate'],
                'permission_callback' => [$this->capabilityChecker, 'permissionWrite'],
                'args'                => $this->getBatchDuplicateArgs(),
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

        // First pass: fetch products without variation counts (avoids a
        // chicken-and-egg problem — we need IDs before we can count variations).
        $products = $builder->getResults();

        // Second pass: hydrate variation counts now that we have product IDs.
        if (! empty($products)) {
            $productIds = array_column($products, 'id');
            $variationCounts = $this->variationsRepository->countsByParents($productIds);

            foreach ($products as &$product) {
                $product['variations_count'] = $variationCounts[$product['id']] ?? 0;
            }
            unset($product);
        }

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
     * DELETE /products/batch — bulk delete products (trash or permanent).
     */
    public function batch_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimitCheck = $this->rateLimiter->check('batch_delete');

        if ($rateLimitCheck instanceof WP_Error) {
            return $rateLimitCheck;
        }

        $mode = (string) ($request->get_param('mode') ?? BulkDelete::MODE_TRASH);

        if (! in_array($mode, [BulkDelete::MODE_TRASH, BulkDelete::MODE_PERMANENT], true)) {
            return $this->error(
                'wbm_invalid_mode',
                __('Invalid delete mode. Expected "trash" or "permanent".', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $rawIds = $request->get_param('ids');

        if (! is_array($rawIds)) {
            return $this->error(
                'wbm_invalid_ids',
                __('No product IDs provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $ids = array_values(array_unique(array_map('intval', $rawIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return $this->error(
                'wbm_invalid_ids',
                __('No valid product IDs provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        if (count($ids) > 500) {
            return $this->error(
                'wbm_too_many_ids',
                __('Too many product IDs in a single request (maximum 500).', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $result = $this->bulkDelete->process($ids, $mode);

        return $this->success($result);
    }

    /**
     * POST /products/duplicate — bulk-duplicate products as drafts.
     */
    public function batch_duplicate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimitCheck = $this->rateLimiter->check('batch_duplicate');

        if ($rateLimitCheck instanceof WP_Error) {
            return $rateLimitCheck;
        }

        $rawIds = $request->get_param('ids');

        if (! is_array($rawIds)) {
            return $this->error(
                'wbm_invalid_ids',
                __('No product IDs provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $ids = array_values(array_unique(array_map('intval', $rawIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return $this->error(
                'wbm_invalid_ids',
                __('No valid product IDs provided.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        if (count($ids) > 100) {
            return $this->error(
                'wbm_too_many_ids',
                __('Too many product IDs in a single request (maximum 100).', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $copyMeta   = (bool) ($request->get_param('copy_meta') ?? true);
        $copyImages = (bool) ($request->get_param('copy_images') ?? true);

        $result = $this->bulkDuplicate->process($ids, $copyMeta, $copyImages);

        return $this->success($result);
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

    private function getBatchDeleteArgs(): array
    {
        return [
            'ids' => [
                'type'     => 'array',
                'required' => true,
                'items'    => [
                    'type' => 'integer',
                ],
            ],
            'mode' => [
                'type'    => 'string',
                'enum'    => [BulkDelete::MODE_TRASH, BulkDelete::MODE_PERMANENT],
                'default' => BulkDelete::MODE_TRASH,
            ],
        ];
    }

    private function getBatchDuplicateArgs(): array
    {
        return [
            'ids' => [
                'type'     => 'array',
                'required' => true,
                'minItems' => 1,
                'maxItems' => 100,
                'items'    => [
                    'type' => 'integer',
                ],
            ],
            'copy_meta' => [
                'type'    => 'boolean',
                'default' => true,
            ],
            'copy_images' => [
                'type'    => 'boolean',
                'default' => true,
            ],
        ];
    }

    private function getQueryArgs(): array
    {
        return [
            'filters' => [
                'type'              => [ 'array', 'object' ],
                'default'           => [],
                'required'          => false,
                'validate_callback' => static function ( $value ): true|\WP_Error {
                    if ( is_array( $value ) ) {
                        return true;
                    }
                    return new \WP_Error(
                        'rest_invalid_param',
                        __( 'filters must be an array of conditions or a filter group object.', 'ihumbak-woo-bulk-edit' ),
                        [ 'status' => 400 ]
                    );
                },
                'sanitize_callback' => static function ( array $value ): array {
                    // Pass through unchanged; FilterParser normalizes both shapes.
                    return $value;
                },
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
