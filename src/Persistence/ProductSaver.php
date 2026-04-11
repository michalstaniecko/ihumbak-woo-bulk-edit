<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use WC_Product;
use WP_Error;

/**
 * Saves individual product field changes via WooCommerce CRUD API.
 *
 * @license GPL-2.0-or-later
 */
final class ProductSaver
{
    /**
     * Map of field keys to WC_Product setter methods.
     *
     * @var array<string, string>
     */
    private const FIELD_SETTERS = [
        'name'           => 'set_name',
        'sku'            => 'set_sku',
        'regular_price'  => 'set_regular_price',
        'sale_price'     => 'set_sale_price',
        'stock_quantity' => 'set_stock_quantity',
        'status'         => 'set_status',
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
     * @return array{status: 'success'|'error', id: int, message?: string}
     */
    public function save(int $productId, array $changes, string $postModified): array
    {
        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product) {
            return [
                'status'  => 'error',
                'id'      => $productId,
                'message' => __('Product not found.', 'ihumbak-woo-bulk-edit'),
            ];
        }

        // Optimistic locking: reject if product was modified since load.
        $currentModified = get_post_field('post_modified', $productId);

        if ($currentModified !== $postModified) {
            return [
                'status'  => 'error',
                'id'      => $productId,
                'message' => __('Product was modified by another user since you loaded it. Please reload and try again.', 'ihumbak-woo-bulk-edit'),
                'code'    => 'wbm_conflict',
            ];
        }

        // Validate and sanitize all changes before applying any.
        $sanitized = [];

        foreach ($changes as $fieldKey => $value) {
            $field = $this->fieldRegistry->get($fieldKey);

            if ($field === null) {
                return [
                    'status'  => 'error',
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
                    'id'      => $productId,
                    'message' => $validation,
                ];
            }

            $sanitized[$fieldKey] = $value;
        }

        // Apply all changes.
        foreach ($sanitized as $fieldKey => $value) {
            $setter = self::FIELD_SETTERS[$fieldKey];
            $product->$setter($value);
        }

        $result = $product->save();

        if ($result === 0) {
            return [
                'status'  => 'error',
                'id'      => $productId,
                'message' => __('Failed to save product.', 'ihumbak-woo-bulk-edit'),
            ];
        }

        return [
            'status' => 'success',
            'id'     => $productId,
        ];
    }
}
