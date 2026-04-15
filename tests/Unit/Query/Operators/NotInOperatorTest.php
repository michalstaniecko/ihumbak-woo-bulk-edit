<?php

/**
 * NotInOperatorTest
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\NotInOperator;
use PHPUnit\Framework\TestCase;

final class NotInOperatorTest extends TestCase
{
    private NotInOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new NotInOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('NOT IN', $this->operator->getIdentifier());
    }

    public function test_toSql_with_array_input(): void
    {
        $result = $this->operator->toSql('col', ['a', 'b']);

        self::assertSame('(col NOT IN (%s, %s) OR col IS NULL)', $result['sql']);
        self::assertSame(['a', 'b'], $result['values']);
    }

    public function test_toSql_with_string_input(): void
    {
        $result = $this->operator->toSql('col', 'foo,bar');

        self::assertSame('(col NOT IN (%s, %s) OR col IS NULL)', $result['sql']);
        self::assertSame(['foo', 'bar'], $result['values']);
    }

    public function test_toSql_strips_empty_values(): void
    {
        $result = $this->operator->toSql('col', ['a', '', 'b']);

        self::assertSame('(col NOT IN (%s, %s) OR col IS NULL)', $result['sql']);
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
}
