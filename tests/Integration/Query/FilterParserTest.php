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
}
