<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\LikeOperator;
use WP_UnitTestCase;

final class LikeOperatorTest extends WP_UnitTestCase
{
    private LikeOperator $operator;

    public function set_up(): void
    {
        parent::set_up();
        $this->operator = new LikeOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('LIKE', $this->operator->getIdentifier());
    }

    public function test_toSql_wraps_value_with_wildcards(): void
    {
        $result = $this->operator->toSql('col', 'shirt');

        self::assertSame('col LIKE %s', $result['sql']);
        self::assertCount(1, $result['values']);
        self::assertStringStartsWith('%', $result['values'][0]);
        self::assertStringEndsWith('%', $result['values'][0]);
        self::assertStringContainsString('shirt', $result['values'][0]);
    }

    public function test_toSql_escapes_percent_in_value(): void
    {
        $result = $this->operator->toSql('col', '50%');

        // esc_like escapes the % character
        self::assertStringNotContainsString('50%', $result['values'][0]);
    }

    public function test_toSql_escapes_underscore_in_value(): void
    {
        $result = $this->operator->toSql('col', 'a_b');

        // esc_like escapes the _ character
        $value = $result['values'][0];
        // Remove wrapping wildcards to inspect inner content
        $inner = substr($value, 1, -1);
        self::assertStringNotContainsString('a_b', $inner);
    }
}
