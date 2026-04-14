<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

/**
 * Central mapping of field keys to WordPress taxonomy slugs.
 *
 * This is the single source of truth for all taxonomy field → WP slug
 * conversions, shared by QueryBuilder, FilterParser, and TaxonomyTermsController.
 *
 * @license GPL-2.0-or-later
 */
final class TaxonomyMap
{
    /**
     * Map of field_key → WordPress taxonomy slug.
     *
     * TODO: integration point for custom taxonomies (Brands, etc.) — see Integrations roadmap.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'categories'     => 'product_cat',
        'tags'           => 'product_tag',
        'shipping_class' => 'product_shipping_class',
    ];

    /**
     * Return all field keys that correspond to a WordPress taxonomy.
     *
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * Convert a field key (e.g. "categories") into a WP taxonomy slug (e.g. "product_cat").
     *
     * @param string $fieldKey The field key used in the filter/API.
     * @return string|null The WP taxonomy slug, or null if not a taxonomy field.
     */
    public static function taxonomyForField(string $fieldKey): ?string
    {
        return self::MAP[$fieldKey] ?? null;
    }

    /**
     * Reverse lookup: WP taxonomy slug → field key.
     *
     * @param string $taxonomy The WP taxonomy slug (e.g. "product_cat").
     * @return string|null The field key, or null if the slug is not in the map.
     */
    public static function fieldForTaxonomy(string $taxonomy): ?string
    {
        $flipped = array_flip(self::MAP);
        return $flipped[$taxonomy] ?? null;
    }

    /**
     * Check whether a given field key corresponds to a WordPress taxonomy.
     *
     * @param string $fieldKey The field key to check.
     */
    public static function isTaxonomyField(string $fieldKey): bool
    {
        return isset(self::MAP[$fieldKey]);
    }

    /**
     * Return a full copy of the field_key → taxonomy slug map.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::MAP;
    }
}
