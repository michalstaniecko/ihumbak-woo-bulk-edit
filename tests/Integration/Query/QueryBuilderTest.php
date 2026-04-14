<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query;

use IhumbakWooBulkEdit\Query\QueryBuilder;
use WP_UnitTestCase;

final class QueryBuilderTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        // WC is not loaded in the integration test bootstrap; register the
        // product taxonomies manually so fixtures with terms can materialize.
        if (! taxonomy_exists('product_cat')) {
            register_taxonomy('product_cat', 'product', ['hierarchical' => true]);
        }
        if (! taxonomy_exists('product_tag')) {
            register_taxonomy('product_tag', 'product', ['hierarchical' => false]);
        }
        if (! taxonomy_exists('product_shipping_class')) {
            register_taxonomy('product_shipping_class', 'product', ['hierarchical' => false]);
        }
    }

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

    // --- Taxonomy filtering ---

    public function test_addTaxonomyCondition_filters_by_category_equal(): void
    {
        $shirtId = $this->createProductWithTerms('Blue Shirt', ['product_cat' => ['Shirts']]);
        $this->createProductWithTerms('Red Hat', ['product_cat' => ['Hats']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name = %s', ['Shirts'], false);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame($shirtId, $results[0]['id']);
    }

    public function test_addTaxonomyCondition_filters_by_category_like_case_insensitive(): void
    {
        $a = $this->createProductWithTerms('Torba A', ['product_cat' => ['Bagasjerom']]);
        $b = $this->createProductWithTerms('Torba B', ['product_cat' => ['bagasjerom duży']]);
        $c = $this->createProductWithTerms('Torba C', ['product_cat' => ['Mini bagasjerom set']]);
        $d = $this->createProductWithTerms('Torba D', ['product_cat' => ['BAGASJEROM Pro']]);
        $this->createProductWithTerms('Nie pasuje', ['product_cat' => ['Hats']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name LIKE %s', ['%bagasjerom%'], false);
        $results = $builder->getResults();

        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertCount(4, $results);
        self::assertContains($a, $ids);
        self::assertContains($b, $ids);
        self::assertContains($c, $ids);
        self::assertContains($d, $ids);
    }

    public function test_addTaxonomyCondition_not_equal_excludes_matching_products(): void
    {
        $this->createProductWithTerms('Shirt Product', ['product_cat' => ['Shirts']]);
        $hat = $this->createProductWithTerms('Hat Product', ['product_cat' => ['Hats']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name = %s', ['Shirts'], true);
        $results = $builder->getResults();

        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($hat, $ids);
        self::assertCount(1, array_filter($ids, fn($id) => $id === $hat));
    }

    public function test_addTaxonomyCondition_not_equal_includes_products_with_no_terms(): void
    {
        $this->createProductWithTerms('Shirt Product', ['product_cat' => ['Shirts']]);
        $noCats = $this->createProduct('No Categories');

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name = %s', ['Shirts'], true);
        $results = $builder->getResults();

        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($noCats, $ids, 'Products with zero terms must match NOT IN');
    }

    public function test_addTaxonomyExistsCondition_empty_matches_products_with_no_categories(): void
    {
        $this->createProductWithTerms('Has Cat', ['product_cat' => ['Shirts']]);
        $noCats = $this->createProduct('No Cat');

        $builder = new QueryBuilder();
        $builder->addTaxonomyExistsCondition('product_cat', false);
        $results = $builder->getResults();

        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($noCats, $ids);
        self::assertCount(1, $results);
    }

    public function test_addTaxonomyExistsCondition_not_empty_matches_products_with_categories(): void
    {
        $hasCat = $this->createProductWithTerms('Has Cat', ['product_cat' => ['Shirts']]);
        $this->createProduct('No Cat');

        $builder = new QueryBuilder();
        $builder->addTaxonomyExistsCondition('product_cat', true);
        $results = $builder->getResults();

        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($hasCat, $ids);
        self::assertCount(1, $results);
    }

    public function test_addTaxonomyCondition_product_with_multiple_matching_categories_not_duplicated(): void
    {
        $multiCat = $this->createProductWithTerms(
            'Multi',
            ['product_cat' => ['Summer Shirts', 'Winter Shirts', 'All Shirts']]
        );

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name LIKE %s', ['%Shirts%'], false);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame($multiCat, $results[0]['id']);
        self::assertSame(1, $builder->getTotal());
    }

    public function test_addTaxonomyCondition_combined_category_and_tag_no_join_explosion(): void
    {
        $match = $this->createProductWithTerms(
            'Match',
            [
                'product_cat' => ['Red', 'Blue', 'Green'],
                'product_tag' => ['Summer', 'Hot', 'Sale'],
            ]
        );
        $this->createProductWithTerms(
            'Cat only',
            ['product_cat' => ['Red']]
        );
        $this->createProductWithTerms(
            'Tag only',
            ['product_tag' => ['Summer']]
        );

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_cat', 't.name = %s', ['Red'], false);
        $builder->addTaxonomyCondition('product_tag', 't.name = %s', ['Summer'], false);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame($match, $results[0]['id']);
        self::assertSame(1, $builder->getTotal());
    }

    public function test_addTaxonomyCondition_filters_by_shipping_class(): void
    {
        $heavy = $this->createProductWithTerms('Heavy', ['product_shipping_class' => ['Heavy Items']]);
        $this->createProductWithTerms('Light', ['product_shipping_class' => ['Light Items']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyCondition('product_shipping_class', 't.name = %s', ['Heavy Items'], false);
        $results = $builder->getResults();

        self::assertCount(1, $results);
        self::assertSame($heavy, $results[0]['id']);
    }

    // --- addTaxonomyTermIdCondition (hierarchical term-id path) ---

    public function test_addTaxonomyTermIdCondition_filters_by_multiple_term_ids(): void
    {
        $termA = wp_insert_term('CatA', 'product_cat');
        $termB = wp_insert_term('CatB', 'product_cat');
        self::assertIsArray($termA);
        self::assertIsArray($termB);

        $idA = (int) $termA['term_id'];
        $idB = (int) $termB['term_id'];

        $productA = $this->createProductWithTerms('Product in A', ['product_cat' => ['CatA']]);
        $productB = $this->createProductWithTerms('Product in B', ['product_cat' => ['CatB']]);
        $this->createProductWithTerms('Product in Hats', ['product_cat' => ['HatsTermId']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyTermIdCondition('product_cat', [$idA, $idB], false);
        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertContains($productA, $ids, 'Product in CatA must be returned');
        self::assertContains($productB, $ids, 'Product in CatB must be returned');
        self::assertCount(2, $results, 'Only products in CatA or CatB must be returned');
    }

    public function test_addTaxonomyTermIdCondition_negated_excludes_all_listed_ids(): void
    {
        $termA = wp_insert_term('NegCatA', 'product_cat');
        $termB = wp_insert_term('NegCatB', 'product_cat');
        $termH = wp_insert_term('NegHats', 'product_cat');
        self::assertIsArray($termA);
        self::assertIsArray($termB);
        self::assertIsArray($termH);

        $idA = (int) $termA['term_id'];
        $idB = (int) $termB['term_id'];

        $productA    = $this->createProductWithTerms('Neg Product A', ['product_cat' => ['NegCatA']]);
        $productB    = $this->createProductWithTerms('Neg Product B', ['product_cat' => ['NegCatB']]);
        $productHats = $this->createProductWithTerms('Neg Product Hats', ['product_cat' => ['NegHats']]);

        $builder = new QueryBuilder();
        $builder->addTaxonomyTermIdCondition('product_cat', [$idA, $idB], true);
        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertContains($productHats, $ids, 'Unrelated product must be returned');
        self::assertNotContains($productA, $ids, 'Product in NegCatA must be excluded');
        self::assertNotContains($productB, $ids, 'Product in NegCatB must be excluded');
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

    /**
     * Helper: create a product with term assignments.
     *
     * @param array<string, list<string>> $termsByTaxonomy
     */
    private function createProductWithTerms(string $title, array $termsByTaxonomy = [], string $status = 'publish'): int
    {
        $id = $this->createProduct($title, [], $status);

        foreach ($termsByTaxonomy as $taxonomy => $termNames) {
            $termIds = [];
            foreach ($termNames as $name) {
                $term = wp_insert_term($name, $taxonomy);
                if (is_wp_error($term)) {
                    $existing = get_term_by('name', $name, $taxonomy);
                    if ($existing) {
                        $termIds[] = (int) $existing->term_id;
                    }
                } else {
                    $termIds[] = (int) $term['term_id'];
                }
            }
            wp_set_object_terms($id, $termIds, $taxonomy);
        }

        return $id;
    }
}
