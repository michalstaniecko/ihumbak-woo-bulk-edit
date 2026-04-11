<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

/**
 * Builds native SQL queries for product filtering.
 *
 * Uses $wpdb->prepare() for all user input — never string concatenation.
 * Queries wp_posts + conditional JOINs on wp_postmeta only when meta conditions exist.
 */
final class QueryBuilder
{
    /** @var list<array{sql: string, values: list<mixed>}> */
    private array $conditions = [];

    /** @var list<array{meta_key: string, alias: string}> */
    private array $metaJoins = [];

    private int $metaJoinCounter = 0;

    private string $orderByField = 'p.post_title';
    private string $orderByDirection = 'ASC';

    private int $page = 1;
    private int $perPage = 50;

    /**
     * Map of field keys to their SQL column references.
     * Fields stored in wp_posts are direct columns; meta fields require a JOIN.
     */
    private const POST_COLUMNS = [
        'name'             => 'p.post_title',
        'slug'             => 'p.post_name',
        'status'           => 'p.post_status',
        'description'      => 'p.post_content',
        'short_description' => 'p.post_excerpt',
        'menu_order'       => 'p.menu_order',
        'date_created'     => 'p.post_date',
        'reviews_allowed'  => 'p.comment_status',
    ];

    private const META_KEYS = [
        'sku'                => '_sku',
        'regular_price'      => '_regular_price',
        'sale_price'         => '_sale_price',
        'stock_quantity'     => '_stock',
        'manage_stock'       => '_manage_stock',
        'backorders'         => '_backorders',
        'sold_individually'  => '_sold_individually',
        'weight'             => '_weight',
        'length'             => '_length',
        'width'              => '_width',
        'height'             => '_height',
        'virtual'            => '_virtual',
        'downloadable'       => '_downloadable',
        'download_limit'     => '_download_limit',
        'download_expiry'    => '_download_expiry',
        'purchase_note'      => '_purchase_note',
        'external_url'       => '_product_url',
        'button_text'        => '_button_text',
        'featured'           => '_featured',
        'catalog_visibility' => '_visibility',
    ];

    /**
     * Add a WHERE condition for a post column.
     */
    public function addPostCondition(string $sql, array $values): self
    {
        $this->conditions[] = ['sql' => $sql, 'values' => $values];
        return $this;
    }

    /**
     * Add a meta JOIN and return the alias. Does not add a WHERE condition.
     */
    public function joinMeta(string $metaKey): string
    {
        // Reuse existing join for the same meta key.
        foreach ($this->metaJoins as $join) {
            if ($join['meta_key'] === $metaKey) {
                return $join['alias'];
            }
        }

        $alias = 'm' . $this->metaJoinCounter++;

        $this->metaJoins[] = [
            'meta_key' => $metaKey,
            'alias'    => $alias,
        ];

        return $alias;
    }

    /**
     * Convenience: join a meta key and add a WHERE condition in one call.
     */
    public function addMetaCondition(string $metaKey, string $conditionSql, array $values): string
    {
        $alias = $this->joinMeta($metaKey);
        $this->conditions[] = ['sql' => $conditionSql, 'values' => $values];
        return $alias;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if (isset(self::POST_COLUMNS[$field])) {
            $this->orderByField = self::POST_COLUMNS[$field];
        } elseif (isset(self::META_KEYS[$field])) {
            $alias = $this->joinMeta(self::META_KEYS[$field]);
            $this->orderByField = "{$alias}.meta_value";
        }

        $this->orderByDirection = $direction;

        return $this;
    }

    public function paginate(int $page, int $perPage): self
    {
        $this->page = max(1, $page);
        $this->perPage = max(1, min(500, $perPage));
        return $this;
    }

    /**
     * Resolve a field key to its SQL column expression.
     * If it's a meta field, creates a JOIN and returns the alias.meta_value.
     *
     * @return string|null SQL column expression, or null if unknown field.
     */
    public function resolveColumn(string $fieldKey): ?string
    {
        if (isset(self::POST_COLUMNS[$fieldKey])) {
            return self::POST_COLUMNS[$fieldKey];
        }

        if (isset(self::META_KEYS[$fieldKey])) {
            return null; // Meta fields need addMetaCondition instead.
        }

        return null;
    }

    /**
     * Check if a field is a meta field.
     */
    public function isMetaField(string $fieldKey): bool
    {
        return isset(self::META_KEYS[$fieldKey]);
    }

    /**
     * Get the meta key for a field.
     */
    public function getMetaKey(string $fieldKey): ?string
    {
        return self::META_KEYS[$fieldKey] ?? null;
    }

    /**
     * Execute query and return total count.
     */
    public function getTotal(): int
    {
        global $wpdb;

        $sql = $this->buildCountSql();

        if (! empty($this->conditions)) {
            $values = $this->collectValues();
            if (! empty($values)) {
                $sql = $wpdb->prepare($sql, ...$values);
            }
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Execute query and return product rows.
     *
     * @return list<array<string, mixed>>
     */
    public function getResults(): array
    {
        global $wpdb;

        $sql = $this->buildSelectSql();

        $values = $this->collectValues();
        if (! empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return $this->hydrateProducts($rows);
    }

    private function buildSelectSql(): string
    {
        global $wpdb;

        $select = "SELECT DISTINCT p.ID, p.post_title AS name, p.post_name AS slug, p.post_status AS status, p.post_content AS description, p.post_excerpt AS short_description, p.menu_order, p.post_date AS date_created, p.comment_status AS reviews_allowed, p.post_modified";
        $from = " FROM {$wpdb->posts} p";
        $joins = $this->buildJoins();
        $where = $this->buildWhere();
        $orderBy = " ORDER BY {$this->orderByField} {$this->orderByDirection}";
        $limit = sprintf(' LIMIT %d OFFSET %d', $this->perPage, ($this->page - 1) * $this->perPage);

        return $select . $from . $joins . $where . $orderBy . $limit;
    }

    private function buildCountSql(): string
    {
        global $wpdb;

        $select = "SELECT COUNT(DISTINCT p.ID)";
        $from = " FROM {$wpdb->posts} p";
        $joins = $this->buildJoins();
        $where = $this->buildWhere();

        return $select . $from . $joins . $where;
    }

    private function buildJoins(): string
    {
        global $wpdb;

        $joins = '';

        foreach ($this->metaJoins as $join) {
            $alias = $join['alias'];
            $metaKey = $join['meta_key'];
            $joins .= " LEFT JOIN {$wpdb->postmeta} {$alias} ON (p.ID = {$alias}.post_id AND {$alias}.meta_key = '{$metaKey}')";
        }

        return $joins;
    }

    private function buildWhere(): string
    {
        $clauses = ["p.post_type IN ('product', 'product_variation')", "p.post_status != 'auto-draft'"];

        foreach ($this->conditions as $condition) {
            $clauses[] = $condition['sql'];
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @return list<mixed>
     */
    private function collectValues(): array
    {
        $values = [];

        foreach ($this->conditions as $condition) {
            foreach ($condition['values'] as $val) {
                $values[] = $val;
            }
        }

        return $values;
    }

    /**
     * Hydrate raw DB rows with meta data.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function hydrateProducts(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        global $wpdb;

        $ids = array_column($rows, 'ID');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $metaKeysToFetch = [
            '_sku', '_regular_price', '_sale_price', '_stock',
            '_manage_stock', '_backorders', '_sold_individually',
            '_weight', '_length', '_width', '_height',
            '_virtual', '_downloadable', '_download_limit', '_download_expiry',
            '_purchase_note', '_product_url', '_button_text',
            '_featured', '_visibility',
            '_thumbnail_id', '_product_image_gallery',
            '_crosssell_ids', '_upsell_ids',
        ];

        $metaPlaceholders = implode(',', array_fill(0, count($metaKeysToFetch), '%s'));

        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
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

        // Fetch taxonomy terms (categories, tags, shipping class).
        $termRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tr.object_id, tt.taxonomy, t.name, t.term_id
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tr.object_id IN ({$placeholders})
                 AND tt.taxonomy IN ('product_cat', 'product_tag', 'product_shipping_class')",
                ...$ids
            ),
            ARRAY_A
        ) ?: [];

        $termMap = [];
        foreach ($termRows as $term) {
            $termMap[(int) $term['object_id']][$term['taxonomy']][] = [
                'id'   => (int) $term['term_id'],
                'name' => $term['name'],
            ];
        }

        $products = [];
        foreach ($rows as $row) {
            $id = (int) $row['ID'];
            $meta = $metaMap[$id] ?? [];
            $terms = $termMap[$id] ?? [];

            $galleryIds = ! empty($meta['_product_image_gallery'])
                ? array_map('intval', explode(',', $meta['_product_image_gallery']))
                : [];

            $crossSellIds = ! empty($meta['_crosssell_ids'])
                ? array_map('intval', maybe_unserialize($meta['_crosssell_ids']))
                : [];

            $upsellIds = ! empty($meta['_upsell_ids'])
                ? array_map('intval', maybe_unserialize($meta['_upsell_ids']))
                : [];

            $products[] = [
                'id'                 => $id,
                'name'               => $row['name'],
                'slug'               => $row['slug'],
                'status'             => $row['status'],
                'description'        => $row['description'] ?? '',
                'short_description'  => $row['short_description'] ?? '',
                'menu_order'         => (int) ($row['menu_order'] ?? 0),
                'date_created'       => $row['date_created'] ?? '',
                'reviews_allowed'    => ($row['reviews_allowed'] ?? 'open') === 'open',
                'sku'                => $meta['_sku'] ?? '',
                'regular_price'      => $meta['_regular_price'] ?? '',
                'sale_price'         => $meta['_sale_price'] ?? '',
                'manage_stock'       => ($meta['_manage_stock'] ?? 'no') === 'yes',
                'stock_quantity'     => isset($meta['_stock']) ? (int) $meta['_stock'] : null,
                'backorders'         => $meta['_backorders'] ?? 'no',
                'sold_individually'  => ($meta['_sold_individually'] ?? 'no') === 'yes',
                'weight'             => $meta['_weight'] ?? '',
                'length'             => $meta['_length'] ?? '',
                'width'              => $meta['_width'] ?? '',
                'height'             => $meta['_height'] ?? '',
                'virtual'            => ($meta['_virtual'] ?? 'no') === 'yes',
                'downloadable'       => ($meta['_downloadable'] ?? 'no') === 'yes',
                'download_limit'     => (int) ($meta['_download_limit'] ?? -1),
                'download_expiry'    => (int) ($meta['_download_expiry'] ?? -1),
                'purchase_note'      => $meta['_purchase_note'] ?? '',
                'external_url'       => $meta['_product_url'] ?? '',
                'button_text'        => $meta['_button_text'] ?? '',
                'featured'           => ($meta['_featured'] ?? 'no') === 'yes',
                'catalog_visibility' => $meta['_visibility'] ?? 'visible',
                'thumbnail_id'       => isset($meta['_thumbnail_id']) ? (int) $meta['_thumbnail_id'] : null,
                'gallery'            => $galleryIds,
                'categories'         => $terms['product_cat'] ?? [],
                'tags'               => $terms['product_tag'] ?? [],
                'shipping_class'     => ! empty($terms['product_shipping_class'])
                    ? $terms['product_shipping_class'][0]['name']
                    : '',
                'cross_sells'        => $crossSellIds,
                'upsells'            => $upsellIds,
                'post_modified'      => $row['post_modified'],
            ];
        }

        return $products;
    }
}
