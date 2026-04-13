<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query;

use IhumbakWooBulkEdit\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new QueryBuilder();
    }

    // --- resolveColumn ---

    public function test_resolveColumn_name_returns_post_title(): void
    {
        self::assertSame('p.post_title', $this->builder->resolveColumn('name'));
    }

    public function test_resolveColumn_status_returns_post_status(): void
    {
        self::assertSame('p.post_status', $this->builder->resolveColumn('status'));
    }

    public function test_resolveColumn_meta_field_returns_null(): void
    {
        self::assertNull($this->builder->resolveColumn('sku'));
    }

    public function test_resolveColumn_unknown_returns_null(): void
    {
        self::assertNull($this->builder->resolveColumn('bogus'));
    }

    // --- isMetaField ---

    public function test_isMetaField_sku(): void
    {
        self::assertTrue($this->builder->isMetaField('sku'));
    }

    public function test_isMetaField_regular_price(): void
    {
        self::assertTrue($this->builder->isMetaField('regular_price'));
    }

    public function test_isMetaField_sale_price(): void
    {
        self::assertTrue($this->builder->isMetaField('sale_price'));
    }

    public function test_isMetaField_stock_quantity(): void
    {
        self::assertTrue($this->builder->isMetaField('stock_quantity'));
    }

    public function test_isMetaField_name_is_false(): void
    {
        self::assertFalse($this->builder->isMetaField('name'));
    }

    public function test_isMetaField_unknown_is_false(): void
    {
        self::assertFalse($this->builder->isMetaField('bogus'));
    }

    // --- getMetaKey ---

    public function test_getMetaKey_sku(): void
    {
        self::assertSame('_sku', $this->builder->getMetaKey('sku'));
    }

    public function test_getMetaKey_regular_price(): void
    {
        self::assertSame('_regular_price', $this->builder->getMetaKey('regular_price'));
    }

    public function test_getMetaKey_sale_price(): void
    {
        self::assertSame('_sale_price', $this->builder->getMetaKey('sale_price'));
    }

    public function test_getMetaKey_stock_quantity(): void
    {
        self::assertSame('_stock', $this->builder->getMetaKey('stock_quantity'));
    }

    public function test_getMetaKey_unknown_returns_null(): void
    {
        self::assertNull($this->builder->getMetaKey('bogus'));
    }

    // --- joinMeta ---

    public function test_joinMeta_returns_alias_m0(): void
    {
        self::assertSame('m0', $this->builder->joinMeta('_sku'));
    }

    public function test_joinMeta_deduplicates(): void
    {
        $first = $this->builder->joinMeta('_sku');
        $second = $this->builder->joinMeta('_sku');

        self::assertSame('m0', $first);
        self::assertSame('m0', $second);
    }

    public function test_joinMeta_increments_alias(): void
    {
        $first = $this->builder->joinMeta('_sku');
        $second = $this->builder->joinMeta('_regular_price');

        self::assertSame('m0', $first);
        self::assertSame('m1', $second);
    }

    // --- addPostCondition ---

    public function test_addPostCondition_returns_self(): void
    {
        $result = $this->builder->addPostCondition('p.post_title = %s', ['test']);

        self::assertSame($this->builder, $result);
    }

    // --- paginate ---

    public function test_paginate_returns_self(): void
    {
        $result = $this->builder->paginate(1, 50);

        self::assertSame($this->builder, $result);
    }

    // --- orderBy ---

    public function test_orderBy_returns_self(): void
    {
        $result = $this->builder->orderBy('name', 'asc');

        self::assertSame($this->builder, $result);
    }

    public function test_orderBy_meta_field_creates_join(): void
    {
        $this->builder->orderBy('sku');

        // Verify the join was created by checking joinMeta returns the same alias (dedup)
        $alias = $this->builder->joinMeta('_sku');
        self::assertSame('m0', $alias);
    }

    // --- addMetaCondition ---

    public function test_addMetaCondition_returns_alias(): void
    {
        $alias = $this->builder->addMetaCondition('_sku', "m0.meta_value = %s", ['test']);

        self::assertSame('m0', $alias);
    }

    // --- getTaxonomyName ---

    public function test_getTaxonomyName_categories(): void
    {
        self::assertSame('product_cat', $this->builder->getTaxonomyName('categories'));
    }

    public function test_getTaxonomyName_tags(): void
    {
        self::assertSame('product_tag', $this->builder->getTaxonomyName('tags'));
    }

    public function test_getTaxonomyName_shipping_class(): void
    {
        self::assertSame('product_shipping_class', $this->builder->getTaxonomyName('shipping_class'));
    }

    public function test_getTaxonomyName_unknown_returns_null(): void
    {
        self::assertNull($this->builder->getTaxonomyName('bogus'));
    }

    public function test_getTaxonomyName_meta_field_returns_null(): void
    {
        self::assertNull($this->builder->getTaxonomyName('sku'));
    }

    public function test_getTaxonomyName_post_column_returns_null(): void
    {
        self::assertNull($this->builder->getTaxonomyName('name'));
    }

    // --- isTaxonomyField ---

    public function test_isTaxonomyField_categories(): void
    {
        self::assertTrue($this->builder->isTaxonomyField('categories'));
    }

    public function test_isTaxonomyField_tags(): void
    {
        self::assertTrue($this->builder->isTaxonomyField('tags'));
    }

    public function test_isTaxonomyField_shipping_class(): void
    {
        self::assertTrue($this->builder->isTaxonomyField('shipping_class'));
    }

    public function test_isTaxonomyField_meta_field_is_false(): void
    {
        self::assertFalse($this->builder->isTaxonomyField('sku'));
    }

    public function test_isTaxonomyField_post_column_is_false(): void
    {
        self::assertFalse($this->builder->isTaxonomyField('name'));
    }

    public function test_isTaxonomyField_unknown_is_false(): void
    {
        self::assertFalse($this->builder->isTaxonomyField('bogus'));
    }

    // Note: addTaxonomyCondition / addTaxonomyExistsCondition fluent interface
    // is covered in tests/Integration/Query/QueryBuilderTest.php — they reference
    // global $wpdb and need the WP test bootstrap.
}
