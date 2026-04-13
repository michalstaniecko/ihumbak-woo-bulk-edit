<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Operations;

use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use Throwable;
use WC_Product;

/**
 * Bulk delete operation: moves products to trash or permanently deletes them.
 *
 * Uses WooCommerce CRUD (`$product->delete()`) so it stays HPOS-safe and routes
 * all writes through the active data store. Captures a shallow snapshot of each
 * product before deletion and logs it to the audit log for later review.
 *
 * Matches the return shape of {@see \IhumbakWooBulkEdit\Persistence\BatchSaver::process()}
 * so the frontend can reuse the same result-handling code.
 *
 * @license GPL-2.0-or-later
 */
final class BulkDelete
{
    public const MODE_TRASH     = 'trash';
    public const MODE_PERMANENT = 'permanent';
    public const FIELD_DELETED  = '_deleted';

    public function __construct(
        private readonly ChangeLogRepository $changeLog,
    ) {}

    /**
     * Process a list of product IDs for deletion.
     *
     * Parent-level accounting keeps the invariant `success + errors === total`
     * where `total = count(unique positive $ids)`. Cascade-delete failures on
     * child variations (only relevant in MODE_PERMANENT for variable products)
     * are tracked separately in `variation_errors` — they do NOT affect the
     * parent's success/errors counters. A failed variation still appends an
     * error row to `results` (with code `wbm_variation_delete_failed`) so the
     * caller can inspect which variations were orphaned, but the sum of rows
     * in `results` may therefore exceed `total`.
     *
     * @param list<int> $ids  Product IDs to delete. Non-positive values are skipped.
     * @param string    $mode Either self::MODE_TRASH or self::MODE_PERMANENT.
     *
     * @return array{
     *     results: list<array{status: string, id: int, message?: string, code?: string}>,
     *     total: int,
     *     success: int,
     *     errors: int,
     *     variation_errors: int,
     *     mode: string
     * }
     */
    public function process(array $ids, string $mode): array
    {
        $mode = $mode === self::MODE_PERMANENT ? self::MODE_PERMANENT : self::MODE_TRASH;

        // De-dupe + cast to positive ints.
        $normalisedIds = [];
        foreach ($ids as $rawId) {
            $intId = (int) $rawId;
            if ($intId > 0) {
                $normalisedIds[$intId] = $intId;
            }
        }
        $normalisedIds = array_values($normalisedIds);

        $results            = [];
        $successCount       = 0;
        $errorCount         = 0;
        $variationErrorCount = 0;
        $logEntries         = [];

        wp_defer_term_counting(true);

        try {
            foreach ($normalisedIds as $productId) {
                $product = wc_get_product($productId);

                if (! $product instanceof WC_Product) {
                    $errorCount++;
                    $results[] = [
                        'status'  => 'error',
                        'id'      => $productId,
                        'code'    => 'wbm_not_found',
                        'message' => __('Product not found.', 'ihumbak-woo-bulk-edit'),
                    ];
                    continue;
                }

                $snapshot = $this->snapshot($product);

                // Capture variation IDs BEFORE destructive op so we can cascade
                // after the parent delete succeeds. Only variable products — not
                // grouped, even though grouped also exposes `get_children()`.
                $variationIds = [];
                if ($mode === self::MODE_PERMANENT && $product->get_type() === 'variable') {
                    foreach ($product->get_children() as $childId) {
                        $variationIds[] = (int) $childId;
                    }
                }

                try {
                    $deleted = $product->delete($mode === self::MODE_PERMANENT);
                } catch (Throwable $e) {
                    $deleted = false;
                }

                if (! $deleted) {
                    $errorCount++;
                    $results[] = [
                        'status'  => 'error',
                        'id'      => $productId,
                        'code'    => 'wbm_delete_failed',
                        'message' => __('Failed to delete product.', 'ihumbak-woo-bulk-edit'),
                    ];
                    continue;
                }

                $successCount++;
                $results[] = [
                    'status' => 'success',
                    'id'     => $productId,
                ];

                $logEntries[] = [
                    'product_id' => $productId,
                    'field'      => self::FIELD_DELETED,
                    'old_value'  => $snapshot,
                    'new_value'  => [
                        'status' => $mode === self::MODE_TRASH ? 'trash' : 'deleted',
                    ],
                ];

                // Cascade to variations for permanently-deleted variable products.
                foreach ($variationIds as $vid) {
                    $variation = wc_get_product($vid);

                    if (! $variation instanceof WC_Product) {
                        // Already cascaded by another hook — skip silently.
                        continue;
                    }

                    $variationSnapshot = $this->snapshot($variation);

                    try {
                        $vDeleted = $variation->delete(true);
                    } catch (Throwable $e) {
                        $vDeleted = false;
                    }

                    if (! $vDeleted) {
                        $variationErrorCount++;
                        $results[] = [
                            'status'  => 'error',
                            'id'      => $vid,
                            'code'    => 'wbm_variation_delete_failed',
                            'message' => __('Failed to delete product variation.', 'ihumbak-woo-bulk-edit'),
                        ];
                        continue;
                    }

                    $logEntries[] = [
                        'product_id' => $vid,
                        'field'      => self::FIELD_DELETED,
                        'old_value'  => $variationSnapshot,
                        'new_value'  => [
                            'status'    => 'deleted',
                            'parent_id' => $productId,
                        ],
                    ];
                }
            }
        } finally {
            wp_defer_term_counting(false);
            wc_delete_product_transients();

            if ($logEntries !== []) {
                $this->changeLog->logBatch($logEntries);
            }
        }

        return [
            'results'          => $results,
            'total'            => count($normalisedIds),
            'success'          => $successCount,
            'errors'           => $errorCount,
            'variation_errors' => $variationErrorCount,
            'mode'             => $mode,
        ];
    }

    /**
     * Build a shallow pre-delete snapshot for the audit log.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     sku: string,
     *     status: string,
     *     type: string,
     *     price: string
     * }
     */
    private function snapshot(WC_Product $product): array
    {
        return [
            'id'     => (int) $product->get_id(),
            'name'   => (string) $product->get_name(),
            'sku'    => (string) $product->get_sku(),
            'status' => (string) $product->get_status(),
            'type'   => (string) $product->get_type(),
            'price'  => (string) $product->get_price(),
        ];
    }
}
