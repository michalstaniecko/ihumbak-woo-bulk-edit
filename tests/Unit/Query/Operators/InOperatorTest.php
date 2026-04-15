<?php

/**
 * InOperatorTest
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\InOperator;
use PHPUnit\Framework\TestCase;

final class InOperatorTest extends TestCase
{
    private InOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new InOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('IN', $this->operator->getIdentifier());
    }

    public function test_toSql_with_array_input(): void
    {
        $result = $this->operator->toSql('col', ['a', 'b', 'c']);

        self::assertSame('col IN (%s, %s, %s)', $result['sql']);
        self::assertSame(['a', 'b', 'c'], $result['values']);
    }

    public function test_toSql_with_string_input(): void
    {
        $result = $this->operator->toSql('col', 'foo,bar,baz');

        self::assertSame('col IN (%s, %s, %s)', $result['sql']);
        self::assertSame(['foo', 'bar', 'baz'], $result['values']);
    }

    public function test_toSql_strips_empty_values(): void
    {
        $result = $this->operator->toSql('col', ['a', '', 'b', '  ']);

        self::assertSame('col IN (%s, %s)', $result['sql']);
        self::assertSame(['a', 'b'], $result['values']);
    }

    public function test_toSql_with_empty_array_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', []);

        self::assertSame('1=0', $result['sql']);
        self::assertSame([], $result['values']);
    }

    public function test_toSql_with_empty_string_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', '');

        self::assertSame('1=0', $result['sql']);
        self::assertSame([], $result['values']);
    }

    public function test_toSql_with_comma_only_string_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', ',,,');

        self::assertSame('1=0', $result['sql']);
        self::assertSame([], $result['values']);
    }
}
