<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

final class LikeOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'LIKE';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "{$column} LIKE %s",
            'values' => ['%' . $GLOBALS['wpdb']->esc_like((string) $value) . '%'],
        ];
    }
}
