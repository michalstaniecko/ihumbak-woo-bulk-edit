<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use WC_Product;
use WC_Product_Variation;
use WP_Error;

/**
 * Saves individual product field changes via WooCommerce CRUD API.
 *
 * @license GPL-2.0-or-later
 */
final class ProductSaver
{
    /**
     * Statuses allowed when editing a product_variation post.
     * Variations may only be 'publish' or 'private'; other statuses (draft,
     * pending, trash, future) do not make sense on variation posts and are
     * rejected before touching the database.
     *
     * @var list<string>
     */
    public const VARIATION_ALLOWED_STATUSES = ['publish', 'private'];

    /**
     * Map of field keys to WC_Product setter methods.
     *
     * @var array<string, string>
     */
    private const FIELD_SETTERS = [
        'name'               => 'set_name',
        'slug'               => 'set_slug',
        'sku'                => 'set_sku',
        'status'             => 'set_status',
        'catalog_visibility' => 'set_catalog_visibility',
        'featured'           => 'set_featured',
        'description'        => 'set_description',
        'short_description'  => 'set_short_description',
        'regular_price'      => 'set_regular_price',
        'sale_price'         => 'set_sale_price',
        'manage_stock'       => 'set_manage_stock',
        'stock_quantity'     => 'set_stock_quantity',
        'backorders'         => 'set_backorders',
        'sold_individually'  => 'set_sold_individually',
        'weight'             => 'set_weight',
        'length'             => 'set_length',
        'width'              => 'set_width',
        'height'             => 'set_height',
        'virtual'            => 'set_virtual',
        'downloadable'       => 'set_downloadable',
        'download_limit'     => 'set_download_limit',
        'download_expiry'    => 'set_download_expiry',
        'external_url'       => 'set_product_url',
        'button_text'        => 'set_button_text',
        'purchase_note'      => 'set_purchase_note',
        'reviews_allowed'    => 'set_reviews_allowed',
        'menu_order'         => 'set_menu_order',
    ];

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {}

    /**
     * Save a set of field changes for a single product.
     *
     * @param int                    $productId    Product ID.
     * @param array<string, mixed>   $changes      Field key => new value.
     * @param string                 $postModified The post_modified timestamp from when the product was loaded.
     *
     * @return array{
     *     status: 'success'|'error',
     *     id: int,
     *     message?: string,
     *     code?: string,
     *     changes?: array<string, array{old: mixed, new: mixed}>
     * }
     */
    public function save(int $productId, array $changes, string $postModified): array
    {
        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product) {
            return [
                'status'  => 'error',
                'code'    => 'wbm_not_found',
                'id'      => $productId,
                'message' => __('Product not found.', 'ihumbak-woo-bulk-edit'),
            ];
        }

        // Optimistic locking: reject if product was modified since load.
        $currentModified = get_post_field('post_modified', $productId);

        if ($currentModified !== $postModified) {
            return [
                'status'  => 'error',
                'code'    => 'wbm_conflict',
                'id'      => $productId,
                'message' => __('Product was modified by another user since you loaded it. Please reload and try again.', 'ihumbak-woo-bulk-edit'),
            ];
        }

        // Validate and sanitize all changes before applying any.
        $sanitized = [];

        foreach ($changes as $fieldKey => $value) {
            $field = $this->fieldRegistry->get($fieldKey);

            if ($field === null) {
                return [
                    'status'  => 'error',
                    'code'    => 'wbm_unknown_field',
                    'id'      => $productId,
                    'message' => sprintf(
                        /* translators: %s: field key */
                        __('Unknown field: %s', 'ihumbak-woo-bulk-edit'),
                        $fieldKey
                    ),
                ];
            }

            if (! $field->isEditable()) {
                return [
                    'status'  => 'error',
                    'code'    => 'wbm_not_editable',
                    'id'      => $productId,
                    'message' => sprintf(
                        /* translators: %s: field label */
                        __('Field "%s" is not editable.', 'ihumbak-woo-bulk-edit'),
                        $field->getLabel()
                    ),
                ];
            }

            if (! isset(self::FIELD_SETTERS[$fieldKey])) {
                return [
                    'status'  => 'error',
                    'code'    => 'wbm_no_setter',
                    'id'      => $productId,
                    'message' => sprintf(
                        /* translators: %s: field key */
                        __('Field "%s" does not support saving yet.', 'ihumbak-woo-bulk-edit'),
                        $fieldKey
                    ),
                ];
            }

            $value = $field->sanitize($value);
            $validation = $field->validate($value, $changes);

            if ($validation !== true) {
                return [
                    'status'  => 'error',
                    'code'    => 'wbm_validation_error',
                    'id'      => $productId,
                    'message' => $validation,
                ];
            }

            $sanitized[$fieldKey] = $value;
        }

        // Guard: variations may only have 'publish' or 'private' status.
        if (
            $product instanceof WC_Product_Variation
            && isset($sanitized['status'])
            && ! in_array($sanitized['status'], self::VARIATION_ALLOWED_STATUSES, true)
        ) {
            return [
                'status'  => 'error',
                'code'    => 'wbm_invalid_variation_status',
                'id'      => $productId,
                'message' => sprintf(
                    /* translators: %s: attempted status value */
                    __('Variation status "%s" is not allowed. Use "publish" or "private".', 'ihumbak-woo-bulk-edit'),
                    $sanitized['status']
                ),
            ];
        }

        // Capture old values (for audit log) and apply all changes.
        $diff = [];

        foreach ($sanitized as $fieldKey => $value) {
            $setter = self::FIELD_SETTERS[$fieldKey];
            $getter = 'get_' . substr($setter, 4);

            $oldValue = method_exists($product, $getter)
                ? $product->$getter('edit')
                : null;

            if (! $this->valuesEqual($oldValue, $value)) {
                $diff[$fieldKey] = [
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }

            $product->$setter($value);
        }

        $result = $product->save();

        if ($result === 0) {
            return [
                'status'  => 'error',
                'code'    => 'wbm_save_failed',
                'id'      => $productId,
                'message' => __('Failed to save product.', 'ihumbak-woo-bulk-edit'),
            ];
        }

        return [
            'status'  => 'success',
            'id'      => $productId,
            'changes' => $diff,
        ];
    }

    /**
     * Loose equality check that normalises scalar/numeric comparisons so that
     * e.g. "10.00" and 10 are treated as equal (they survive the WC setters
     * round-trip identically).
     */
    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_scalar($a) && is_scalar($b)) {
            return (string) $a === (string) $b;
        }

        return wp_json_encode($a) === wp_json_encode($b);
    }
}
