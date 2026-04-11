<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query;

use IhumbakWooBulkEdit\Query\QueryBuilder;
use WP_UnitTestCase;

final class QueryBuilderTest extends WP_UnitTestCase
{
    public function test_getResults_returns_empty_for_no_products(): void
    {
        $builder = new QueryBuilder();
        $results = $builder->getResults();

        self::assertSame([], $results);
    }

    public function test_getTotal_returns_zero_for_no_products(): void
    {
        $builder = new QueryBuilder();
        $total = $builder->getTotal();

        self::assertSame(0, $total);
    }

    public function test_getResults_returns_products(): void
    {
        $this->createProduct('Product A');
        $this->createProduct('Product B');
        $this->createProduct('Product C');

        $builder = new QueryBuilder();
        $results = $builder->getResults();

        self::assertCount(3, $results);
    }

    public function test_getResults_hydrates_meta(): void
    {
        $id = $this->createProduct('Test Product', [
            '_sku' => 'SKU-001',
            '_regular_price' => '19.99',
            '_sale_price' => '14.99',
            '_stock' => '50',
        ]);

        $builder = new QueryBuilder();
        $results = $builder->getResults();

        self::assertCount(1, $results);

        $product = $results[0];
        self::assertSame($id, $product['id']);
        self::assertSame('Test Product', $product['name']);
        self::assertSame('SKU-001', $product['sku']);
        self::assertSame('19.99', $product['regular_price']);
        self::assertSame('14.99', $product['sale_price']);
        self::assertSame(50, $product['stock_quantity']);
    }

    public function test_getResults_has_expected_keys(): void
    {
        $this->createProduct('Test');

        $builder = new QueryBuilder();
        $results = $builder->getResults();
        $product = $results[0];

        self::assertArrayHasKey('id', $product);
        self::assertArrayHasKey('name', $product);
        self::assertArrayHasKey('status', $product);
        self::assertArrayHasKey('sku', $product);
        self::assertArrayHasKey('regular_price', $product);
        self::assertArrayHasKey('sale_price', $product);
        self::assertArrayHasKey('stock_quantity', $product);
        self::assertArrayHasKey('post_modified', $product);
        self::assertArrayHasKey('thumbnail_id', $product);
    }

    public function test_orderBy_post_field_asc(): void
    {
        $this->createProduct('Zulu');
        $this->createProduct('Alpha');

        $builder = new QueryBuilder();
        $builder->orderBy('name', 'asc');
        $results = $builder->getResults();

        self::assertSame('Alpha', $results[0]['name']);
        self::assertSame('Zulu', $results[1]['name']);
    }

    public function test_orderBy_direction_desc(): void
    {
        $this->createProduct('Alpha');
        $this->createProduct('Zulu');

        $builder = new QueryBuilder();
        $builder->orderBy('name', 'desc');
        $results = $builder->getResults();

        self::assertSame('Zulu', $results[0]['name']);
        self::assertSame('Alpha', $results[1]['name']);
    }

    public function test_paginate_limits_results(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createProduct("Product {$i}");
        }

        $builder = new QueryBuilder();
        $builder->paginate(1, 2);
        $results = $builder->getResults();

        self::assertCount(2, $results);
    }

    public function test_paginate_offset(): void
    {
        $this->createProduct('Alpha');
        $this->createProduct('Bravo');
        $this->createProduct('Charlie');

        $builder = new QueryBuilder();
        $builder->orderBy('name', 'asc');
        $builder->paginate(2, 2);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame('Charlie', $results[0]['name']);
    }

    public function test_getTotal_with_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createProduct("Product {$i}");
        }

        $builder = new QueryBuilder();
        $builder->paginate(1, 2);

        // Total should reflect all matching rows, not just the page
        self::assertSame(5, $builder->getTotal());
    }

    public function test_addPostCondition_filters_results(): void
    {
        $this->createProduct('Published', [], 'publish');
        $this->createProduct('Draft', [], 'draft');

        $builder = new QueryBuilder();
        $builder->addPostCondition('p.post_status = %s', ['publish']);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame('Published', $results[0]['name']);
    }

    public function test_addMetaCondition_filters_by_meta(): void
    {
        $this->createProduct('With SKU', ['_sku' => 'ABC']);
        $this->createProduct('Other SKU', ['_sku' => 'XYZ']);

        $builder = new QueryBuilder();
        $alias = $builder->addMetaCondition('_sku', "m0.meta_value = %s", ['ABC']);

        self::assertSame('m0', $alias);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame('ABC', $results[0]['sku']);
    }

    public function test_excludes_auto_draft(): void
    {
        $this->createProduct('Real', [], 'publish');
        $this->createProduct('Auto Draft', [], 'auto-draft');

        $builder = new QueryBuilder();
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame('Real', $results[0]['name']);
    }

    /**
     * Helper: create a product post directly via wp_insert_post.
     *
     * @param array<string, string> $meta
     */
    private function createProduct(string $title, array $meta = [], string $status = 'publish'): int
    {
        $id = wp_insert_post([
            'post_title'  => $title,
            'post_type'   => 'product',
            'post_status' => $status,
        ]);

        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        return (int) $id;
    }
}
