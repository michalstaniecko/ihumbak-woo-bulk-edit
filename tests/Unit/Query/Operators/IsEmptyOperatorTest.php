<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\IsEmptyOperator;
use PHPUnit\Framework\TestCase;

final class IsEmptyOperatorTest extends TestCase
{
    private IsEmptyOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new IsEmptyOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('IS EMPTY', $this->operator->getIdentifier());
    }

    public function test_toSql_contains_null_and_empty_check(): void
    {
        $result = $this->operator->toSql('col', null);

        self::assertStringContainsString('IS NULL', $result['sql']);
        self::assertStringContainsString("= ''", $result['sql']);
    }

    public function test_toSql_values_are_empty(): void
    {
        $result = $this->operator->toSql('col', null);

        self::assertSame([], $result['values']);
    }

    public function test_toSql_ignores_value_parameter(): void
    {
        $result = $this->operator->toSql('col', 'some_value');

        self::assertSame([], $result['values']);
    }
}
