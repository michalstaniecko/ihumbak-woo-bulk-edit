<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

use IhumbakWooBulkEdit\Fields\TaxonomyMap;

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

    // NOTE: The taxonomy map has been extracted to TaxonomyMap (Issue #45).
    // getTaxonomyName() and isTaxonomyField() now delegate to TaxonomyMap.

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
     * Add a raw WHERE condition.
     */
    public function addCondition(string $conditionSql, array $values = []): self
    {
        $this->conditions[] = ['sql' => $conditionSql, 'values' => $values];
        return $this;
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
     * Get the WordPress taxonomy slug for a field key.
     *
     * Delegates to TaxonomyMap — single source of truth (Issue #45).
     *
     * @return string|null Taxonomy slug (e.g. "product_cat"), or null if the field is not a taxonomy.
     */
    public function getTaxonomyName(string $fieldKey): ?string
    {
        return TaxonomyMap::taxonomyForField($fieldKey);
    }

    /**
     * Check if a field is a taxonomy field.
     *
     * Delegates to TaxonomyMap — single source of truth (Issue #45).
     */
    public function isTaxonomyField(string $fieldKey): bool
    {
        return TaxonomyMap::isTaxonomyField($fieldKey);
    }

    /**
     * Add a WHERE condition that matches products whose terms in $taxonomy
     * satisfy the given clause on t.name.
     *
     * Implemented as p.ID IN (...) / p.ID NOT IN (...) subquery — never JOINs,
     * so row multiplication from products with multiple matching terms cannot occur.
     *
     * Edge case with $negate=true: products that have ZERO terms in $taxonomy also
     * match (they have no term violating the clause). This is the intended semantics
     * of "this product is not in category X" / "this product has no term like X".
     *
     * @param string      $taxonomy      WP taxonomy slug (e.g. "product_cat")
     * @param string      $nameClauseSql SQL clause produced by an operator on column "t.name"
     * @param list<mixed> $values        Placeholder values for $nameClauseSql
     * @param bool        $negate        If true, wrap with NOT IN
     */
    public function addTaxonomyCondition(string $taxonomy, string $nameClauseSql, array $values, bool $negate): self
    {
        $this->conditions[] = $this->buildTaxonomySubquerySql($taxonomy, $nameClauseSql, $values, $negate);
        return $this;
    }

    /**
     * Add a WHERE condition that matches products whose terms in $taxonomy
     * have a term_id in the given list.
     *
     * Unlike addTaxonomyCondition() — which JOINs wp_terms for name matching —
     * this method operates entirely on wp_term_taxonomy.tt.term_id, so no extra
     * wp_terms JOIN is needed.  Used by the hierarchical-category path in
     * FilterParser where a numeric term_id is expanded to include all descendants
     * before calling this method.
     *
     * Emits:
     *   p.ID {NOT} IN (
     *       SELECT tr.object_id
     *       FROM wp_term_relationships tr
     *       INNER JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
     *       WHERE tt.taxonomy = %s AND tt.term_id IN (%d, %d, …)
     *   )
     *
     * @param string    $taxonomy WP taxonomy slug (e.g. "product_cat")
     * @param list<int> $termIds  One or more term IDs to match; method is a no-op if empty
     * @param bool      $negate   If true, wrap with NOT IN
     */
    public function addTaxonomyTermIdCondition(string $taxonomy, array $termIds, bool $negate): self
    {
        if (empty($termIds)) {
            return $this;
        }
        $this->conditions[] = $this->buildTaxonomyTermIdSql($taxonomy, $termIds, $negate);
        return $this;
    }

    /**
     * Add a WHERE condition for "has any / has no" terms in a taxonomy.
     *
     * @param string $taxonomy WP taxonomy slug
     * @param bool   $exists   true → product has at least one term; false → product has zero terms
     */
    public function addTaxonomyExistsCondition(string $taxonomy, bool $exists): self
    {
        $result = $this->buildTaxonomyExistsSql($taxonomy, $exists);
        $this->conditions[] = $result;
        return $this;
    }

    // ── "Build" helpers — return SQL/values without pushing ───────────────

    /**
     * Build SQL for a taxonomy name-clause condition without pushing to the builder.
     *
     * @param string      $taxonomy      WP taxonomy slug
     * @param string      $nameClauseSql SQL clause on column "t.name"
     * @param list<mixed> $values        Bound values for $nameClauseSql
     * @param bool        $negate        If true, wrap with NOT IN
     * @return array{sql: string, values: list<mixed>}
     */
    public function buildTaxonomySubquerySql(string $taxonomy, string $nameClauseSql, array $values, bool $negate): array
    {
        global $wpdb;

        $not = $negate ? 'NOT ' : '';
        $sql = "p.ID {$not}IN (
            SELECT tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE tt.taxonomy = %s AND {$nameClauseSql}
        )";

        return [
            'sql'    => $sql,
            'values' => array_merge([$taxonomy], $values),
        ];
    }

    /**
     * Build SQL for a taxonomy term_id membership condition without pushing to the builder.
     *
     * @param string    $taxonomy WP taxonomy slug
     * @param list<int> $termIds  One or more term IDs; returns tautology if empty
     * @param bool      $negate   If true, wrap with NOT IN
     * @return array{sql: string, values: list<mixed>}
     */
    public function buildTaxonomyTermIdSql(string $taxonomy, array $termIds, bool $negate): array
    {
        if (empty($termIds)) {
            return ['sql' => '1=1', 'values' => []];
        }

        global $wpdb;

        $not          = $negate ? 'NOT ' : '';
        $placeholders = implode(', ', array_fill(0, count($termIds), '%d'));

        $sql = "p.ID {$not}IN (
            SELECT tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = %s AND tt.term_id IN ({$placeholders})
        )";

        return [
            'sql'    => $sql,
            'values' => array_merge([$taxonomy], $termIds),
        ];
    }

    /**
     * Build SQL for a taxonomy exists / not-exists check without pushing to the builder.
     *
     * @param string $taxonomy WP taxonomy slug
     * @param bool   $exists   true → IN; false → NOT IN
     * @return array{sql: string, values: list<mixed>}
     */
    public function buildTaxonomyExistsSql(string $taxonomy, bool $exists): array
    {
        global $wpdb;

        $in  = $exists ? 'IN' : 'NOT IN';
        $sql = "p.ID {$in} (
            SELECT tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = %s
        )";

        return [
            'sql'    => $sql,
            'values' => [$taxonomy],
        ];
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
     * @param array<int, int> $variationCounts Optional parentId => count map (from VariationsRepository).
     * @return list<array<string, mixed>>
     */
    public function getResults(array $variationCounts = []): array
    {
        global $wpdb;

        $sql = $this->buildSelectSql();

        $values = $this->collectValues();
        if (! empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return $this->hydrateProducts($rows, $variationCounts);
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
            $alias   = $join['alias'];   // safe: always "m" + integer counter
            $metaKey = $join['meta_key'];

            // Use $wpdb->prepare() for $metaKey even though all callers today
            // pass values from the hardcoded META_KEYS constant. This keeps the
            // method safe if a future code path (e.g. custom meta) passes an
            // arbitrary string.
            $joins .= $wpdb->prepare(
                " LEFT JOIN {$wpdb->postmeta} {$alias} ON (p.ID = {$alias}.post_id AND {$alias}.meta_key = %s)",
                $metaKey
            );
        }

        return $joins;
    }

    private function buildWhere(): string
    {
        $clauses = ["p.post_type = 'product'", "p.post_status != 'auto-draft'"];

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
     * Hydrate raw DB rows with meta data, product type, and variation counts.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, int>            $variationCounts parentId => count
     * @return list<array<string, mixed>>
     */
    private function hydrateProducts(array $rows, array $variationCounts = []): array
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

        // Fetch taxonomy terms for all known taxonomies (built-in + custom).
        // TaxonomyMap::all() returns the complete field_key → taxonomy_slug map,
        // so we use all its slug values to build the IN clause dynamically.
        $taxonomyMap = TaxonomyMap::all();
        $allTaxonomySlugs = array_values($taxonomyMap);

        if (empty($allTaxonomySlugs)) {
            $allTaxonomySlugs = ['product_cat', 'product_tag', 'product_shipping_class'];
        }

        $taxonomyPlaceholders = implode(',', array_fill(0, count($allTaxonomySlugs), '%s'));

        $termRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tr.object_id, tt.taxonomy, t.name, t.term_id
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tr.object_id IN ({$placeholders})
                 AND tt.taxonomy IN ({$taxonomyPlaceholders})",
                ...array_merge($ids, $allTaxonomySlugs)
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

        // Fetch product type from the 'product_type' term taxonomy.
        $typeRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tr.object_id, t.slug AS product_type
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tr.object_id IN ({$placeholders})
                 AND tt.taxonomy = 'product_type'",
                ...$ids
            ),
            ARRAY_A
        ) ?: [];

        $typeMap = [];
        foreach ($typeRows as $typeRow) {
            $typeMap[(int) $typeRow['object_id']] = $typeRow['product_type'];
        }

        // Build a map of custom taxonomy field keys (non-built-in) to their taxonomy slugs.
        // Built-in taxonomies (categories, tags, shipping_class) are handled explicitly below.
        $builtinFieldKeys = ['categories', 'tags', 'shipping_class'];
        $customTaxonomyFields = [];
        foreach ($taxonomyMap as $fieldKey => $taxonomySlug) {
            if (!in_array($fieldKey, $builtinFieldKeys, true)) {
                $customTaxonomyFields[$fieldKey] = $taxonomySlug;
            }
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

            // Build custom taxonomy term data keyed by field key.
            $customTaxonomyData = [];
            foreach ($customTaxonomyFields as $fieldKey => $taxonomySlug) {
                $customTaxonomyData[$fieldKey] = $terms[$taxonomySlug] ?? [];
            }

            $products[] = array_merge(
                [
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
                ],
                $customTaxonomyData,
                [
                    'post_modified'    => $row['post_modified'],
                    'type'             => $typeMap[$id] ?? 'simple',
                    'variations_count' => $variationCounts[$id] ?? 0,
                ]
            );
        }

        return $products;
    }
}
