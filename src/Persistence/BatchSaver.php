<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

/**
 * Orchestrates batch saving of product changes.
 *
 * Groups changes by product, processes in configurable batch sizes,
 * defers term counting for performance, and cleans transients once at the end.
 *
 * @license GPL-2.0-or-later
 */
final class BatchSaver
{
    private const DEFAULT_BATCH_SIZE = 50;
    private const MIN_BATCH_SIZE     = 10;
    private const MAX_BATCH_SIZE     = 500;

    public function __construct(
        private readonly ProductSaver $productSaver,
    ) {}

    /**
     * Process a list of product changes in batches.
     *
     * Each change item must have: id (int), field (string), value (mixed), post_modified (string).
     *
     * @param list<array{id: int, field: string, value: mixed, post_modified: string}> $changes
     * @param int $batchSize Number of products per batch.
     *
     * @return array{
     *     results: list<array{status: string, id: int, message?: string}>,
     *     total: int,
     *     success: int,
     *     errors: int
     * }
     */
    public function process(array $changes, int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $batchSize = max(self::MIN_BATCH_SIZE, min(self::MAX_BATCH_SIZE, $batchSize));

        // Group changes by product ID: [productId => [field => value, ...]]
        $grouped = $this->groupByProduct($changes);

        $productIds = array_keys($grouped);
        $batches = array_chunk($productIds, $batchSize);

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        wp_defer_term_counting(true);

        try {
            foreach ($batches as $batch) {
                foreach ($batch as $productId) {
                    $productChanges = $grouped[$productId]['fields'];
                    $postModified = $grouped[$productId]['post_modified'];

                    $result = $this->productSaver->save(
                        $productId,
                        $productChanges,
                        $postModified
                    );

                    $results[] = $result;

                    if ($result['status'] === 'success') {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        } finally {
            wp_defer_term_counting(false);
            wc_delete_product_transients();
        }

        return [
            'results' => $results,
            'total'   => count($productIds),
            'success' => $successCount,
            'errors'  => $errorCount,
        ];
    }

    /**
     * Group flat changes array into per-product structure.
     *
     * @param list<array{id: int, field: string, value: mixed, post_modified: string}> $changes
     *
     * @return array<int, array{fields: array<string, mixed>, post_modified: string}>
     */
    private function groupByProduct(array $changes): array
    {
        $grouped = [];

        foreach ($changes as $change) {
            $id = (int) $change['id'];

            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'fields'        => [],
                    'post_modified' => $change['post_modified'],
                ];
            }

            $grouped[$id]['fields'][$change['field']] = $change['value'];
        }

        return $grouped;
    }
}
