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

    // --- term_id filtering (Issue #45) ---

    public function test_apply_category_equal_by_term_id(): void
    {
        // Create categories.
        $shirtsTerm = wp_insert_term('ShirtsByID', 'product_cat');
        wp_insert_term('HatsByID', 'product_cat');

        self::assertIsArray($shirtsTerm);
        $shirtsId = (int) $shirtsTerm['term_id'];

        $shirt = $this->createProductWithTermsByIds('Shirt ID test', ['product_cat' => [$shirtsId]]);
        $hat = $this->createProductWithTerms('Hat ID test', ['product_cat' => ['HatsByID']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => (string) $shirtsId],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertContains($shirt, $ids);
        self::assertNotContains($hat, $ids);
    }

    public function test_apply_category_not_equal_by_term_id_excludes_matching(): void
    {
        $shirtsTerm = wp_insert_term('ShirtsNEQ', 'product_cat');
        wp_insert_term('HatsNEQ', 'product_cat');

        self::assertIsArray($shirtsTerm);
        $shirtsId = (int) $shirtsTerm['term_id'];

        $shirt = $this->createProductWithTermsByIds('Shirt NEQ', ['product_cat' => [$shirtsId]]);
        $hat = $this->createProductWithTerms('Hat NEQ', ['product_cat' => ['HatsNEQ']]);
        $noCat = $this->createProduct('No Cat NEQ');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '!=', 'value' => (string) $shirtsId],
        ]);

        $ids = array_map(fn($r) => $r['id'], $builder->getResults());
        self::assertContains($hat, $ids);
        self::assertContains($noCat, $ids);
        self::assertNotContains($shirt, $ids);
    }

    public function test_apply_category_like_with_numeric_value_uses_name_path(): void
    {
        // When operator is LIKE and value happens to be a numeric string,
        // it should still filter by t.name (backward compat).
        // Create a category whose name IS the numeric string.
        wp_insert_term('12345', 'product_cat');
        wp_insert_term('999', 'product_cat');

        $matchProduct = $this->createProductWithTerms('Numeric Cat Match', ['product_cat' => ['12345']]);
        $this->createProductWithTerms('Other Product', ['product_cat' => ['999']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => 'LIKE', 'value' => '12345'],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($matchProduct, $ids);
    }

    public function test_apply_tag_equal_by_term_id(): void
    {
        $summerTerm = wp_insert_term('SummerTagByID', 'product_tag');
        wp_insert_term('WinterTagByID', 'product_tag');

        self::assertIsArray($summerTerm);
        $summerId = (int) $summerTerm['term_id'];

        $summerProduct = $this->createProductWithTermsByIds('Summer Product', ['product_tag' => [$summerId]]);
        $winterProduct = $this->createProductWithTerms('Winter Product', ['product_tag' => ['WinterTagByID']]);

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'tags', 'operator' => '=', 'value' => (string) $summerId],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);
        self::assertContains($summerProduct, $ids);
        self::assertNotContains($winterProduct, $ids);
    }

    // --- Hierarchical category filtering (subcategory expansion) ---

    public function test_apply_category_equal_by_term_id_includes_child_term_products(): void
    {
        // Create parent "Belysning" and child "Lampor".
        $belysningTerm = wp_insert_term('Belysning', 'product_cat');
        self::assertIsArray($belysningTerm);
        $belysningId = (int) $belysningTerm['term_id'];

        $lamporTerm = wp_insert_term('Lampor', 'product_cat', ['parent' => $belysningId]);
        self::assertIsArray($lamporTerm);
        $lamporId = (int) $lamporTerm['term_id'];

        $directParent = $this->createProductWithTermsByIds('Direct Parent Product', ['product_cat' => [$belysningId]]);
        $childProduct  = $this->createProductWithTermsByIds('Child Product', ['product_cat' => [$lamporId]]);

        $this->primeHierarchyCache('product_cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => (string) $belysningId],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertContains($directParent, $ids, 'Product directly in Belysning must be returned');
        self::assertContains($childProduct, $ids, 'Product in child category Lampor must also be returned');
    }

    public function test_apply_category_equal_by_term_id_excludes_sibling_branches(): void
    {
        $belysningTerm = wp_insert_term('BelysningExcl', 'product_cat');
        self::assertIsArray($belysningTerm);
        $belysningId = (int) $belysningTerm['term_id'];

        $lamporTerm = wp_insert_term('LamporExcl', 'product_cat', ['parent' => $belysningId]);
        self::assertIsArray($lamporTerm);
        $lamporId = (int) $lamporTerm['term_id'];

        $hatsTerm = wp_insert_term('HatsExcl', 'product_cat');
        self::assertIsArray($hatsTerm);
        $hatsId = (int) $hatsTerm['term_id'];

        $lamporProduct = $this->createProductWithTermsByIds('Lampor Product', ['product_cat' => [$lamporId]]);
        $hatsProduct   = $this->createProductWithTermsByIds('Hats Product', ['product_cat' => [$hatsId]]);

        $this->primeHierarchyCache('product_cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => (string) $belysningId],
        ]);

        $ids = array_map(fn($r) => $r['id'], $builder->getResults());

        self::assertContains($lamporProduct, $ids, 'Child-category product must be included');
        self::assertNotContains($hatsProduct, $ids, 'Unrelated-branch product must be excluded');
    }

    public function test_apply_category_not_equal_by_term_id_excludes_descendants(): void
    {
        $belysningTerm = wp_insert_term('BelysningNEQ', 'product_cat');
        self::assertIsArray($belysningTerm);
        $belysningId = (int) $belysningTerm['term_id'];

        $lamporTerm = wp_insert_term('LamporNEQ', 'product_cat', ['parent' => $belysningId]);
        self::assertIsArray($lamporTerm);
        $lamporId = (int) $lamporTerm['term_id'];

        $hatsTerm = wp_insert_term('HatsNEQ2', 'product_cat');
        self::assertIsArray($hatsTerm);
        $hatsId = (int) $hatsTerm['term_id'];

        $parentProduct = $this->createProductWithTermsByIds('Belysning Product NEQ', ['product_cat' => [$belysningId]]);
        $childProduct  = $this->createProductWithTermsByIds('Lampor Product NEQ', ['product_cat' => [$lamporId]]);
        $hatsProduct   = $this->createProductWithTermsByIds('Hats Product NEQ', ['product_cat' => [$hatsId]]);

        $this->primeHierarchyCache('product_cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '!=', 'value' => (string) $belysningId],
        ]);

        $ids = array_map(fn($r) => $r['id'], $builder->getResults());

        self::assertContains($hatsProduct, $ids, 'Unrelated product must be returned by != filter');
        self::assertNotContains($parentProduct, $ids, 'Direct parent-category product must be excluded');
        self::assertNotContains($childProduct, $ids, 'Child-category product must also be excluded');
    }

    public function test_apply_category_equal_by_term_id_leaf_with_no_children_still_matches(): void
    {
        // A leaf term has no children; behavior should be identical to original single-term path.
        $leafTerm = wp_insert_term('LeafOnly', 'product_cat');
        self::assertIsArray($leafTerm);
        $leafId = (int) $leafTerm['term_id'];

        $leafProduct = $this->createProductWithTermsByIds('Leaf Product', ['product_cat' => [$leafId]]);
        $this->createProductWithTerms('Other Product Leaf', ['product_cat' => ['OtherLeaf']]);

        $this->primeHierarchyCache('product_cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => (string) $leafId],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertCount(1, $results, 'Exactly one product should match the leaf term');
        self::assertContains($leafProduct, $ids);
    }

    public function test_apply_category_equal_by_term_id_deep_multi_level_hierarchy(): void
    {
        // Three levels: Belysning → Lampor → LED
        $belysningTerm = wp_insert_term('BelysningDeep', 'product_cat');
        self::assertIsArray($belysningTerm);
        $belysningId = (int) $belysningTerm['term_id'];

        $lamporTerm = wp_insert_term('LamporDeep', 'product_cat', ['parent' => $belysningId]);
        self::assertIsArray($lamporTerm);
        $lamporId = (int) $lamporTerm['term_id'];

        $ledTerm = wp_insert_term('LEDDeep', 'product_cat', ['parent' => $lamporId]);
        self::assertIsArray($ledTerm);
        $ledId = (int) $ledTerm['term_id'];

        $level1Product = $this->createProductWithTermsByIds('Level 1 Product', ['product_cat' => [$belysningId]]);
        $level2Product = $this->createProductWithTermsByIds('Level 2 Product', ['product_cat' => [$lamporId]]);
        $level3Product = $this->createProductWithTermsByIds('Level 3 Product', ['product_cat' => [$ledId]]);

        $this->primeHierarchyCache('product_cat');

        $builder = new QueryBuilder();
        $this->parser->apply($builder, [
            ['field' => 'categories', 'operator' => '=', 'value' => (string) $belysningId],
        ]);

        $results = $builder->getResults();
        $ids = array_map(fn($r) => $r['id'], $results);

        self::assertContains($level1Product, $ids, 'Level 1 (direct) product must be returned');
        self::assertContains($level2Product, $ids, 'Level 2 (child) product must be returned');
        self::assertContains($level3Product, $ids, 'Level 3 (grandchild) product must be returned');
    }

    /**
     * Helper: clear the WordPress hierarchy cache for a taxonomy so that
     * get_term_children() returns fresh results after inserting new terms.
     */
    private function primeHierarchyCache(string $taxonomy): void
    {
        delete_option($taxonomy . '_children');
        wp_cache_delete($taxonomy . '_children', 'terms');
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

    /**
     * Helper: create a product with pre-existing term IDs (avoids name lookup).
     *
     * @param array<string, list<int>> $termsByTaxonomy taxonomy => list of term_ids
     */
    private function createProductWithTermsByIds(string $title, array $termsByTaxonomy = []): int
    {
        $id = $this->createProduct($title);

        foreach ($termsByTaxonomy as $taxonomy => $termIds) {
            wp_set_object_terms($id, $termIds, $taxonomy);
        }

        return $id;
    }

}
