<?php

/**
 * BetweenOperatorTest
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\BetweenOperator;
use PHPUnit\Framework\TestCase;

final class BetweenOperatorTest extends TestCase
{
    private BetweenOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new BetweenOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('BETWEEN', $this->operator->getIdentifier());
    }

    public function test_toSql_with_array_input(): void
    {
        $result = $this->operator->toSql('col', ['10', '20']);

        self::assertSame('col + 0 BETWEEN %s AND %s', $result['sql']);
        self::assertSame(['10', '20'], $result['values']);
    }

    public function test_toSql_with_string_input(): void
    {
        $result = $this->operator->toSql('col', '10,20');

        self::assertSame('col + 0 BETWEEN %s AND %s', $result['sql']);
        self::assertSame(['10', '20'], $result['values']);
    }

    public function test_toSql_with_malformed_array_too_short_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', ['10']);

        self::assertSame('1=0', $result['sql']);
        self::assertSame([], $result['values']);
    }

    public function test_toSql_with_empty_array_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', []);

        self::assertSame('1=0', $result['sql']);
        self::assertSame([], $result['values']);
    }

    public function test_toSql_with_malformed_string_returns_fail_closed(): void
    {
        $result = $this->operator->toSql('col', 'only_one_value');

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
