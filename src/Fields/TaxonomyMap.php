<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

/**
 * Central mapping of field keys to WordPress taxonomy slugs.
 *
 * This is the single source of truth for all taxonomy field → WP slug
 * conversions, shared by QueryBuilder, FilterParser, and TaxonomyTermsController.
 *
 * Runtime entries (custom taxonomies discovered via discoverCustomTaxonomies())
 * are stored in the mutable static $map. Built-in entries are preserved in
 * BUILTIN and are never overwritten by register().
 *
 * @license GPL-2.0-or-later
 */
final class TaxonomyMap
{
    /**
     * Built-in field_key → WordPress taxonomy slug entries.
     * These entries cannot be overwritten via register().
     *
     * @var array<string, string>
     */
    private const BUILTIN = [
        'categories'     => 'product_cat',
        'tags'           => 'product_tag',
        'shipping_class' => 'product_shipping_class',
    ];

    /**
     * Runtime map of field_key → WordPress taxonomy slug.
     * Initialized from BUILTIN; extended at runtime by register().
     *
     * @var array<string, string>
     */
    private static array $map = self::BUILTIN;

    /**
     * Register a custom taxonomy field mapping at runtime.
     *
     * Silently skips the registration if $fieldKey is already a built-in key,
     * preventing accidental overwrite of core taxonomy mappings.
     *
     * @param string $fieldKey    The field key used in the filter/API (e.g. "product_brand").
     * @param string $taxonomySlug The WP taxonomy slug (e.g. "pwb-brand").
     */
    public static function register(string $fieldKey, string $taxonomySlug): void
    {
        if (isset(self::BUILTIN[$fieldKey])) {
            return;
        }

        self::$map[$fieldKey] = $taxonomySlug;
    }

    /**
     * Reset runtime entries back to built-in only.
     *
     * Intended for use in test setUp/tearDown to ensure test isolation.
     */
    public static function resetRuntimeEntries(): void
    {
        self::$map = self::BUILTIN;
    }

    /**
     * Return all field keys that correspond to a WordPress taxonomy.
     *
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return array_keys(self::$map);
    }

    /**
     * Convert a field key (e.g. "categories") into a WP taxonomy slug (e.g. "product_cat").
     *
     * @param string $fieldKey The field key used in the filter/API.
     * @return string|null The WP taxonomy slug, or null if not a taxonomy field.
     */
    public static function taxonomyForField(string $fieldKey): ?string
    {
        return self::$map[$fieldKey] ?? null;
    }

    /**
     * Reverse lookup: WP taxonomy slug → field key.
     *
     * @param string $taxonomy The WP taxonomy slug (e.g. "product_cat").
     * @return string|null The field key, or null if the slug is not in the map.
     */
    public static function fieldForTaxonomy(string $taxonomy): ?string
    {
        $flipped = array_flip(self::$map);
        return $flipped[$taxonomy] ?? null;
    }

    /**
     * Check whether a given field key corresponds to a WordPress taxonomy.
     *
     * @param string $fieldKey The field key to check.
     */
    public static function isTaxonomyField(string $fieldKey): bool
    {
        return isset(self::$map[$fieldKey]);
    }

    /**
     * Return a full copy of the field_key → taxonomy slug map.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$map;
    }
}
