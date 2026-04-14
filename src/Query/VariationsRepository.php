<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

/**
 * Fetches WooCommerce product variations via native SQL.
 *
 * All queries use $wpdb->prepare() — no string concatenation with user input.
 */
final class VariationsRepository
{
    /**
     * Fetch all variations for a given variable product, ordered by menu_order ASC.
     *
     * Returns an empty array if the parent has no variations (e.g. a simple product
     * or a non-existent ID).
     *
     * @param int $parentId WooCommerce parent product ID.
     * @return list<array<string, mixed>>
     */
    public function fetchByParent(int $parentId): array
    {
        global $wpdb;

        $metaKeysToFetch = [
            '_sku', '_regular_price', '_sale_price', '_stock',
            '_manage_stock', '_weight', '_length', '_width', '_height',
            '_thumbnail_id',
        ];

        $metaPlaceholders = implode(',', array_fill(0, count($metaKeysToFetch), '%s'));

        // Fetch variation post rows.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title AS name, p.post_status AS status,
                        p.menu_order, p.post_modified, p.post_parent AS parent_id
                 FROM {$wpdb->posts} p
                 WHERE p.post_type = 'product_variation'
                   AND p.post_status != 'auto-draft'
                   AND p.post_parent = %d
                 ORDER BY p.menu_order ASC",
                $parentId
            ),
            ARRAY_A
        ) ?: [];

        if (empty($rows)) {
            return [];
        }

        $ids = array_column($rows, 'ID');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // Fetch meta for all variations in a single query.
        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN ({$placeholders})
                 AND meta_key IN ({$metaPlaceholders})",
                ...array_merge($ids, $metaKeysToFetch)
            ),
            ARRAY_A
        ) ?: [];

        $metaMap = [];
        foreach ($metaRows as $meta) {
            $metaMap[(int) $meta['post_id']][$meta['meta_key']] = $meta['meta_value'];
        }

        // Fetch variation attribute meta (keys beginning with 'attribute_').
        $attrRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN ({$placeholders})
                 AND meta_key LIKE 'attribute_%'",
                ...$ids
            ),
            ARRAY_A
        ) ?: [];

        $attrMap = [];
        foreach ($attrRows as $attr) {
            $postId = (int) $attr['post_id'];
            // Strip "attribute_" prefix to get the taxonomy/attribute name.
            $attrName = substr($attr['meta_key'], strlen('attribute_'));
            $attrMap[$postId][$attrName] = $attr['meta_value'];
        }

        $variations = [];
        foreach ($rows as $row) {
            $id   = (int) $row['ID'];
            $meta = $metaMap[$id] ?? [];

            $variations[] = [
                'id'             => $id,
                'parent_id'      => (int) $row['parent_id'],
                'name'           => $row['name'],
                'sku'            => $meta['_sku'] ?? '',
                'regular_price'  => $meta['_regular_price'] ?? '',
                'sale_price'     => $meta['_sale_price'] ?? '',
                'stock_quantity' => isset($meta['_stock']) ? (int) $meta['_stock'] : null,
                'manage_stock'   => ($meta['_manage_stock'] ?? 'no') === 'yes',
                'weight'         => $meta['_weight'] ?? '',
                'length'         => $meta['_length'] ?? '',
                'width'          => $meta['_width'] ?? '',
                'height'         => $meta['_height'] ?? '',
                'thumbnail_id'   => isset($meta['_thumbnail_id']) ? (int) $meta['_thumbnail_id'] : null,
                'status'         => $row['status'],
                'menu_order'     => (int) $row['menu_order'],
                'attributes'     => $attrMap[$id] ?? [],
                'post_modified'  => $row['post_modified'],
            ];
        }

        return $variations;
    }

    /**
     * Return a map of parentId => variation count for multiple parents at once.
     *
     * Parents with zero variations are omitted from the returned map; callers
     * should default to 0 for missing keys.
     *
     * @param list<int> $parentIds
     * @return array<int, int> parentId => count
     */
    public function countsByParents(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($parentIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_parent AS parent_id, COUNT(*) AS cnt
                 FROM {$wpdb->posts}
                 WHERE post_type = 'product_variation'
                   AND post_status != 'auto-draft'
                   AND post_parent IN ({$placeholders})
                 GROUP BY post_parent",
                ...$parentIds
            ),
            ARRAY_A
        ) ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['parent_id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
