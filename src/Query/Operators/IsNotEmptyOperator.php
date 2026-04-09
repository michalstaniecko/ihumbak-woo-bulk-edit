<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

final class IsNotEmptyOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'IS NOT EMPTY';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "({$column} IS NOT NULL AND {$column} != '')",
            'values' => [],
        ];
    }
}
