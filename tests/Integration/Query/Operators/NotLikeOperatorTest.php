<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\NotLikeOperator;
use WP_UnitTestCase;

final class NotLikeOperatorTest extends WP_UnitTestCase
{
    private NotLikeOperator $operator;

    public function set_up(): void
    {
        parent::set_up();
        $this->operator = new NotLikeOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('NOT LIKE', $this->operator->getIdentifier());
    }

    public function test_toSql_wraps_value_with_wildcards(): void
    {
        $result = $this->operator->toSql('col', 'test');

        self::assertSame('col NOT LIKE %s', $result['sql']);
        self::assertSame('%test%', $result['values'][0]);
    }

    public function test_toSql_escapes_special_characters(): void
    {
        $result = $this->operator->toSql('col', '50%off');

        $value = $result['values'][0];
        $inner = substr($value, 1, -1);
        self::assertStringNotContainsString('50%off', $inner);
    }
}
