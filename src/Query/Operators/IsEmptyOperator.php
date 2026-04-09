<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

final class IsEmptyOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'IS EMPTY';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "({$column} IS NULL OR {$column} = '')",
            'values' => [],
        ];
    }
}
