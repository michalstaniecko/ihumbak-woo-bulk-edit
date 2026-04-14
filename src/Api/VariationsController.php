<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Query\VariationsRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WC_Product;
use WC_Product_Variable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for product variations.
 *
 * Route: GET /products/{id}/variations
 */
final class VariationsController extends RestController
{
    protected $rest_base = 'products';

    public function __construct(
        private readonly VariationsRepository $variationsRepository,
        private readonly CapabilityChecker $capabilityChecker,
    ) {}

    public function register_routes(): void
    {
        register_rest_route(
            $this->getNamespace(),
            '/' . $this->rest_base . '/(?P<id>\d+)/variations',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_variations'],
                    'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
                    'args'                => [
                        'id' => [
                            'type'     => 'integer',
                            'required' => true,
                            'minimum'  => 1,
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * GET /products/{id}/variations — return all variations for a variable product.
     */
    public function get_variations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $parentId = absint($request->get_param('id'));

        $product = wc_get_product($parentId);

        if (! $product instanceof WC_Product) {
            return $this->error(
                'wbm_not_found',
                __('Product not found.', 'ihumbak-woo-bulk-edit'),
                404
            );
        }

        if (! $product instanceof WC_Product_Variable) {
            return $this->error(
                'wbm_not_variable',
                __('Product is not a variable product.', 'ihumbak-woo-bulk-edit'),
                400
            );
        }

        $items = $this->variationsRepository->fetchByParent($parentId);

        return $this->success([
            'parent_id' => $parentId,
            'items'     => $items,
            'total'     => count($items),
        ]);
    }
}
