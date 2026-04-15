<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields;

use IhumbakWooBulkEdit\Fields\TaxonomyMap;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TaxonomyMap.
 *
 * @covers \IhumbakWooBulkEdit\Fields\TaxonomyMap
 */
final class TaxonomyMapTest extends TestCase
{
    protected function setUp(): void
    {
        TaxonomyMap::resetRuntimeEntries();
    }

    protected function tearDown(): void
    {
        TaxonomyMap::resetRuntimeEntries();
    }

    public function test_fieldKeys_returns_all_three_keys(): void
    {
        $keys = TaxonomyMap::fieldKeys();

        self::assertContains('categories', $keys);
        self::assertContains('tags', $keys);
        self::assertContains('shipping_class', $keys);
        self::assertCount(3, $keys);
    }

    public function test_taxonomyForField_returns_correct_slug_for_categories(): void
    {
        self::assertSame('product_cat', TaxonomyMap::taxonomyForField('categories'));
    }

    public function test_taxonomyForField_returns_correct_slug_for_tags(): void
    {
        self::assertSame('product_tag', TaxonomyMap::taxonomyForField('tags'));
    }

    public function test_taxonomyForField_returns_correct_slug_for_shipping_class(): void
    {
        self::assertSame('product_shipping_class', TaxonomyMap::taxonomyForField('shipping_class'));
    }

    public function test_taxonomyForField_returns_null_for_unknown_key(): void
    {
        self::assertNull(TaxonomyMap::taxonomyForField('nonexistent'));
        self::assertNull(TaxonomyMap::taxonomyForField(''));
        self::assertNull(TaxonomyMap::taxonomyForField('name'));
        self::assertNull(TaxonomyMap::taxonomyForField('regular_price'));
    }

    public function test_fieldForTaxonomy_returns_categories_for_product_cat(): void
    {
        self::assertSame('categories', TaxonomyMap::fieldForTaxonomy('product_cat'));
    }

    public function test_fieldForTaxonomy_returns_tags_for_product_tag(): void
    {
        self::assertSame('tags', TaxonomyMap::fieldForTaxonomy('product_tag'));
    }

    public function test_fieldForTaxonomy_returns_shipping_class_for_product_shipping_class(): void
    {
        self::assertSame('shipping_class', TaxonomyMap::fieldForTaxonomy('product_shipping_class'));
    }

    public function test_fieldForTaxonomy_returns_null_for_unknown_slug(): void
    {
        self::assertNull(TaxonomyMap::fieldForTaxonomy('product_type'));
        self::assertNull(TaxonomyMap::fieldForTaxonomy(''));
        self::assertNull(TaxonomyMap::fieldForTaxonomy('nonexistent'));
    }

    public function test_isTaxonomyField_returns_true_for_known_fields(): void
    {
        self::assertTrue(TaxonomyMap::isTaxonomyField('categories'));
        self::assertTrue(TaxonomyMap::isTaxonomyField('tags'));
        self::assertTrue(TaxonomyMap::isTaxonomyField('shipping_class'));
    }

    public function test_isTaxonomyField_returns_false_for_unknown_fields(): void
    {
        self::assertFalse(TaxonomyMap::isTaxonomyField('name'));
        self::assertFalse(TaxonomyMap::isTaxonomyField('sku'));
        self::assertFalse(TaxonomyMap::isTaxonomyField('regular_price'));
        self::assertFalse(TaxonomyMap::isTaxonomyField(''));
        self::assertFalse(TaxonomyMap::isTaxonomyField('nonexistent'));
    }

    public function test_all_returns_complete_map(): void
    {
        $map = TaxonomyMap::all();

        self::assertIsArray($map);
        self::assertArrayHasKey('categories', $map);
        self::assertArrayHasKey('tags', $map);
        self::assertArrayHasKey('shipping_class', $map);
        self::assertSame('product_cat', $map['categories']);
        self::assertSame('product_tag', $map['tags']);
        self::assertSame('product_shipping_class', $map['shipping_class']);
        self::assertCount(3, $map);
    }

    public function test_all_returns_a_copy_not_reference(): void
    {
        $map1 = TaxonomyMap::all();
        $map2 = TaxonomyMap::all();

        // Modifying one should not affect the other.
        $map1['extra'] = 'extra_tax';
        self::assertArrayNotHasKey('extra', $map2);
    }

    public function test_fieldKeys_returns_list_of_strings(): void
    {
        $keys = TaxonomyMap::fieldKeys();

        self::assertIsArray($keys);
        foreach ($keys as $key) {
            self::assertIsString($key);
        }
    }

    public function test_roundtrip_field_to_taxonomy_and_back(): void
    {
        foreach (TaxonomyMap::fieldKeys() as $fieldKey) {
            $taxonomy = TaxonomyMap::taxonomyForField($fieldKey);
            self::assertNotNull($taxonomy, "taxonomyForField({$fieldKey}) should not be null");
            self::assertSame($fieldKey, TaxonomyMap::fieldForTaxonomy($taxonomy));
        }
    }

    // ── Runtime registration tests ────────────────────────────────────────

    public function test_register_adds_runtime_entry(): void
    {
        TaxonomyMap::register('product_brand', 'pwb-brand');

        self::assertSame('pwb-brand', TaxonomyMap::taxonomyForField('product_brand'));
        self::assertSame('product_brand', TaxonomyMap::fieldForTaxonomy('pwb-brand'));
        self::assertTrue(TaxonomyMap::isTaxonomyField('product_brand'));
        self::assertContains('product_brand', TaxonomyMap::fieldKeys());
    }

    public function test_register_does_not_overwrite_builtin(): void
    {
        // Attempt to re-register 'categories' with a different slug.
        TaxonomyMap::register('categories', 'some_other_tax');

        // Built-in mapping must remain unchanged.
        self::assertSame('product_cat', TaxonomyMap::taxonomyForField('categories'));
    }

    public function test_isTaxonomyField_returns_true_for_runtime_entry(): void
    {
        TaxonomyMap::register('custom_tax', 'my_custom_taxonomy');

        self::assertTrue(TaxonomyMap::isTaxonomyField('custom_tax'));
    }

    public function test_fieldForTaxonomy_works_for_runtime_entry(): void
    {
        TaxonomyMap::register('custom_tax', 'my_custom_taxonomy');

        self::assertSame('custom_tax', TaxonomyMap::fieldForTaxonomy('my_custom_taxonomy'));
    }

    public function test_resetRuntimeEntries_restores_builtin_only(): void
    {
        TaxonomyMap::register('custom_tax', 'my_custom_taxonomy');
        self::assertContains('custom_tax', TaxonomyMap::fieldKeys());

        TaxonomyMap::resetRuntimeEntries();

        self::assertNotContains('custom_tax', TaxonomyMap::fieldKeys());
        self::assertCount(3, TaxonomyMap::fieldKeys());
        // Built-ins are still intact.
        self::assertSame('product_cat', TaxonomyMap::taxonomyForField('categories'));
        self::assertSame('product_tag', TaxonomyMap::taxonomyForField('tags'));
        self::assertSame('product_shipping_class', TaxonomyMap::taxonomyForField('shipping_class'));
    }
}
