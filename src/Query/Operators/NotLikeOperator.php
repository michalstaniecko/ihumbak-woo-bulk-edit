<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

final class NotLikeOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'NOT LIKE';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "{$column} NOT LIKE %s",
            'values' => ['%' . $GLOBALS['wpdb']->esc_like((string) $value) . '%'],
        ];
    }
}
