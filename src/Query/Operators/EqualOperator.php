<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

final class EqualOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return '=';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "{$column} = %s",
            'values' => [(string) $value],
        ];
    }
}
