<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Operations;

use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use Throwable;
use WC_Admin_Duplicate_Product;
use WC_Product;

/**
 * Bulk duplicate operation: copies a list of products, each as a draft.
 *
 * Wraps WooCommerce core's {@see \WC_Admin_Duplicate_Product::product_duplicate()}
 * so the heavy lifting (HPOS-safe CRUD, unique SKU/slug generation, variation
 * cascade, the `woocommerce_product_duplicate*` action hooks) all happens in
 * core code and stays in lock-step with WC upgrades. We add only:
 *
 *  - opt-out for product meta and product images
 *  - audit-log entries (one per parent duplicate, via `ChangeLogRepository::logBatch`)
 *  - structured success/error result aggregation
 *  - batch hygiene: `wp_defer_term_counting`, `wc_delete_product_transients`
 *
 * Matches the response shape of {@see BulkDelete::process()} so the frontend
 * can reuse the same result-handling code.
 *
 * @license GPL-2.0-or-later
 */
final class BulkDuplicate
{
    public const FIELD_DUPLICATED = '_duplicated';

    /**
     * Meta keys that are always excluded from a duplicate, even when
     * `$copyMeta === true`. These represent runtime/sales state that should
     * never be carried over because they describe the *source* product's
     * lifecycle, not the new draft's.
     *
     * @var list<string>
     */
    private const ALWAYS_EXCLUDED_META = [
        'total_sales',
        '_wc_review_count',
        '_wc_rating_count',
        '_wc_average_rating',
        '_edit_lock',
        '_edit_last',
    ];

    private ?WC_Admin_Duplicate_Product $duplicator = null;

    public function __construct(
        private readonly ChangeLogRepository $changeLog,
    ) {}

    /**
     * Process a list of product IDs for duplication.
     *
     * @param list<int> $ids        Product IDs to duplicate. Non-positive values are skipped.
     * @param bool      $copyMeta   When false, every non-WC-core meta key on the source
     *                              is excluded from the duplicate (in addition to
     *                              `self::ALWAYS_EXCLUDED_META`).
     * @param bool      $copyImages When false, the new product's featured image and
     *                              gallery are cleared (WC core natively shares the
     *                              same attachment IDs — no file is cloned in either
     *                              mode; deep image copy is out of scope).
     *
     * @return array{
     *     results: list<array{status: string, id: int, new_id?: int, message?: string, code?: string}>,
     *     total: int,
     *     success: int,
     *     errors: int
     * }
     */
    public function process(array $ids, bool $copyMeta, bool $copyImages): array
    {
        // De-dupe + cast to positive ints (mirrors BulkDelete).
        $normalisedIds = [];
        foreach ($ids as $rawId) {
            $intId = (int) $rawId;
            if ($intId > 0) {
                $normalisedIds[$intId] = $intId;
            }
        }
        $normalisedIds = array_values($normalisedIds);

        $results      = [];
        $successCount = 0;
        $errorCount   = 0;
        $logEntries   = [];

        // Build the meta-exclusion filter closure.
        // We always exclude ALWAYS_EXCLUDED_META; when $copyMeta is false we
        // additionally exclude every non-WC custom meta key on the source.
        $excludeFilter = function (array $excluded, array $existingMetaKeys) use ($copyMeta): array {
            $excluded = array_merge($excluded, self::ALWAYS_EXCLUDED_META);

            if (! $copyMeta) {
                foreach ($existingMetaKeys as $key) {
                    // Skip WC-core meta (those starting with `_`) so we don't
                    // accidentally drop SKU, price, stock, etc. Custom meta
                    // (third-party plugins, ACF) typically does not start with
                    // an underscore — and even when it does, those plugins
                    // should manage their own duplicate-meta filter.
                    if (is_string($key) && $key !== '' && $key[0] !== '_') {
                        $excluded[] = $key;
                    }
                }
            }

            return array_values(array_unique($excluded));
        };

        add_filter('woocommerce_duplicate_product_exclude_meta', $excludeFilter, 10, 2);

        wp_defer_term_counting(true);

        try {
            foreach ($normalisedIds as $productId) {
                $source = wc_get_product($productId);

                if (! $source instanceof WC_Product) {
                    $errorCount++;
                    $results[] = [
                        'status'  => 'error',
                        'id'      => $productId,
                        'code'    => 'wbm_not_found',
                        'message' => __('Product not found.', 'ihumbak-woo-bulk-edit'),
                    ];
                    continue;
                }

                $sourceName = (string) $source->get_name();

                try {
                    $duplicate = $this->getDuplicator()->product_duplicate($source);
                } catch (Throwable $e) {
                    $duplicate = null;
                }

                if (! $duplicate instanceof WC_Product) {
                    $errorCount++;
                    $results[] = [
                        'status'  => 'error',
                        'id'      => $productId,
                        'code'    => 'wbm_duplicate_failed',
                        'message' => __('Failed to duplicate product.', 'ihumbak-woo-bulk-edit'),
                    ];
                    continue;
                }

                // Strip image references when not copying images. This is a
                // ref-only clear: the source product still owns the attachments.
                if (! $copyImages) {
                    try {
                        $duplicate->set_image_id(0);
                        $duplicate->set_gallery_image_ids([]);
                        $duplicate->save();
                    } catch (Throwable $e) {
                        // Non-fatal — duplicate was created, just couldn't strip images.
                        // Treat as success but note: the duplicate will keep the
                        // source's image references.
                    }
                }

                $newId = (int) $duplicate->get_id();

                $variationCount = 0;
                if ($duplicate->get_type() === 'variable') {
                    $variationCount = count($duplicate->get_children());
                }

                $successCount++;
                $results[] = [
                    'status' => 'success',
                    'id'     => $productId,
                    'new_id' => $newId,
                ];

                // One audit-log entry per parent duplicate. Variations are
                // counted but not logged individually (per spec).
                $logEntries[] = [
                    'product_id' => $newId,
                    'field'      => self::FIELD_DUPLICATED,
                    'old_value'  => null,
                    'new_value'  => [
                        'status'      => 'draft',
                        'source_id'   => $productId,
                        'source_name' => $sourceName,
                        'variations'  => $variationCount,
                        'copy_meta'   => $copyMeta,
                        'copy_images' => $copyImages,
                    ],
                ];
            }
        } finally {
            wp_defer_term_counting(false);
            wc_delete_product_transients();
            remove_filter('woocommerce_duplicate_product_exclude_meta', $excludeFilter, 10);

            if ($logEntries !== []) {
                $this->changeLog->logBatch($logEntries);
            }
        }

        return [
            'results' => $results,
            'total'   => count($normalisedIds),
            'success' => $successCount,
            'errors'  => $errorCount,
        ];
    }

    /**
     * Memoised accessor for WC core's duplicator. Loads the file lazily because
     * it lives under `wp-admin` and isn't autoloaded outside admin contexts.
     */
    private function getDuplicator(): WC_Admin_Duplicate_Product
    {
        if ($this->duplicator !== null) {
            return $this->duplicator;
        }

        if (! class_exists(WC_Admin_Duplicate_Product::class, false)) {
            $path = WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php';
            if (is_readable($path)) {
                require_once $path;
            }
        }

        $this->duplicator = new WC_Admin_Duplicate_Product();

        return $this->duplicator;
    }
}
