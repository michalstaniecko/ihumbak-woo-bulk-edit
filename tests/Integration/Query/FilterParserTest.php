<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query;

use IhumbakWooBulkEdit\Fields\FieldInterface;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Query\FilterParser;
use IhumbakWooBulkEdit\Query\QueryBuilder;
use WP_Error;
use WP_UnitTestCase;

final class FilterParserTest extends WP_UnitTestCase
{
    private FilterParser $parser;
    private FieldRegistry $registry;

    public function set_up(): void
    {
        parent::set_up();
        $this->registry = new FieldRegistry();
        $this->parser = new FilterParser($this->registry);

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

    public function test_apply_empty_filters_returns_true(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, []);

        self::assertTrue($result);
    }

    public function test_apply_unknown_field_returns_wp_error(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'nonexistent', 'operator' => '=', 'value' => 'test'],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_invalid_filter_field', $result->get_error_code());
    }

    public function test_apply_unknown_operator_returns_wp_error(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'name', 'operator' => '>=', 'value' => 'test'],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_invalid_operator', $result->get_error_code());
    }

    public function test_apply_valid_post_field_filter(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'name', 'operator' => '=', 'value' => 'Test'],
        ]);

        self::assertTrue($result);
    }

    public function test_apply_valid_meta_field_filter(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'sku', 'operator' => '=', 'value' => 'ABC'],
        ]);

        self::assertTrue($result);
    }

    public function test_apply_multiple_filters(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'name', 'operator' => 'LIKE', 'value' => 'shirt'],
            ['field' => 'status', 'operator' => '=', 'value' => 'publish'],
        ]);

        self::assertTrue($result);
    }

    public function test_error_includes_filter_index(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'name', 'operator' => '=', 'value' => 'ok'],
            ['field' => 'bogus', 'operator' => '=', 'value' => 'fail'],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertStringContainsString('1', $result->get_error_message());
    }

    public function test_non_filterable_field_returns_wp_error(): void
    {
        $nonFilterable = $this->createMock(FieldInterface::class);
        $nonFilterable->method('getKey')->willReturn('readonly');
        $nonFilterable->method('isFilterable')->willReturn(false);

        $this->registry->register($nonFilterable);

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'readonly', 'operator' => '=', 'value' => 'test'],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_field_not_filterable', $result->get_error_code());
    }

    // --- Taxonomy filtering (regression for issue #35) ---

    public function test_apply_category_filter_like_returns_matching_products(): void
    {
        $a = $this->createProductWithTerms('Torba A', ['product_cat' => ['Bagasjerom']]);
        $b = $this->createProductWithTerms('Torba B', ['product_cat' => ['bagasjerom duży']]);
        $c = $this->createProductWithTerms('Torba C', ['product_cat' => ['Mini bagasjerom set']]);
        $d = $this->createProductWithTerms('Torba D', ['product_cat' => ['BAGASJEROM Pro']]);
        $this->createProductWithTerms('Nie pasuje', ['product_cat' => ['Hats']]);

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => 'LIKE', 'value' => 'bagasjerom'],
        ]);

        self::assertTrue($result);
        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertSame(4, $builder->getTotal());
        self::assertCount(4, $results);
        self::assertContains($a, $ids);
        self::assertContains($b, $ids);
        self::assertContains($c, $ids);
        self::assertContains($d, $ids);
    }

    public function test_apply_category_equal_filter(): void
    {
        $shirt = $this->createProductWithTerms('Shirt', ['product_cat' => ['Shirts']]);
        $this->createProductWithTerms('Hat', ['product_cat' => ['Hats']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => 'Shirts'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($shirt, $results[0]['id']);
    }

    public function test_apply_category_not_equal_filter_excludes_matching_and_includes_empty(): void
    {
        $shirt = $this->createProductWithTerms('Shirt', ['product_cat' => ['Shirts']]);
        $hat = $this->createProductWithTerms('Hat', ['product_cat' => ['Hats']]);
        $noCat = $this->createProduct('No Category');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '!=', 'value' => 'Shirts'],
        ]);

        $ids = array_map(fn($r) => $r['id'], $builder->getResults());
        self::assertContains($hat, $ids);
        self::assertContains($noCat, $ids);
        self::assertNotContains($shirt, $ids);
    }

    public function test_apply_category_not_like_filter(): void
    {
        $this->createProductWithTerms('Match A', ['product_cat' => ['Bagasjerom']]);
        $other = $this->createProductWithTerms('Other', ['product_cat' => ['Shirts']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => 'NOT LIKE', 'value' => 'bagasjerom'],
        ]);

        $ids = array_map(fn($r) => $r['id'], $builder->getResults());
        self::assertContains($other, $ids);
        self::assertCount(1, $ids);
    }

    public function test_apply_category_is_empty_filter(): void
    {
        $this->createProductWithTerms('With Cat', ['product_cat' => ['Shirts']]);
        $empty = $this->createProduct('No Cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => 'IS EMPTY'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($empty, $results[0]['id']);
    }

    public function test_apply_category_is_not_empty_filter(): void
    {
        $withCat = $this->createProductWithTerms('With Cat', ['product_cat' => ['Shirts']]);
        $this->createProduct('No Cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => 'IS NOT EMPTY'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($withCat, $results[0]['id']);
    }

    public function test_apply_tag_filter(): void
    {
        $match = $this->createProductWithTerms('Summer Item', ['product_tag' => ['summer', 'hot']]);
        $this->createProductWithTerms('Winter Item', ['product_tag' => ['winter', 'cold']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'tags', 'operator' => 'LIKE', 'value' => 'summer'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($match, $results[0]['id']);
    }

    public function test_apply_shipping_class_filter(): void
    {
        $heavy = $this->createProductWithTerms('Heavy', ['product_shipping_class' => ['Heavy Items']]);
        $this->createProductWithTerms('Light', ['product_shipping_class' => ['Light Items']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'shipping_class', 'operator' => '=', 'value' => 'Heavy Items'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($heavy, $results[0]['id']);
    }

    public function test_apply_category_and_meta_filter_combined(): void
    {
        $match = $this->createProductWithTerms('Shirt 10', ['product_cat' => ['Shirts']]);
        update_post_meta($match, '_regular_price', '10');

        $other = $this->createProductWithTerms('Shirt 20', ['product_cat' => ['Shirts']]);
        update_post_meta($other, '_regular_price', '20');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => 'Shirts'],
            ['field' => 'regular_price', 'operator' => '=', 'value' => '10'],
        ]);

        $results = $builder->getResults();
        self::assertCount(1, $results);
        self::assertSame($match, $results[0]['id']);
    }

    /**
     * Helper: create a product post directly via wp_insert_post.
     */
    private function createProduct(string $title, string $status = 'publish'): int
    {
        return (int) wp_insert_post([
            'post_title'  => $title,
            'post_type'   => 'product',
            'post_status' => $status,
        ]);
    }

    /**
     * Helper: create a product with term assignments.
     *
     * @param array<string, list<string>> $termsByTaxonomy
     */
    private function createProductWithTerms(string $title, array $termsByTaxonomy = []): int
    {
        $id = $this->createProduct($title);

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
