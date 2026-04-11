<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\IsNotEmptyOperator;
use PHPUnit\Framework\TestCase;

final class IsNotEmptyOperatorTest extends TestCase
{
    private IsNotEmptyOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new IsNotEmptyOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('IS NOT EMPTY', $this->operator->getIdentifier());
    }

    public function test_toSql_contains_not_null_and_not_empty_check(): void
    {
        $result = $this->operator->toSql('col', null);

        self::assertStringContainsString('IS NOT NULL', $result['sql']);
        self::assertStringContainsString("!= ''", $result['sql']);
    }

    public function test_toSql_values_are_empty(): void
    {
        $result = $this->operator->toSql('col', null);

        self::assertSame([], $result['values']);
    }
}
