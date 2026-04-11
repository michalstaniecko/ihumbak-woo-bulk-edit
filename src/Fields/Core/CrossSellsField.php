<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class CrossSellsField extends AbstractField
{
    public function getKey(): string
    {
        return 'cross_sells';
    }

    public function getLabel(): string
    {
        return __('Cross-Sells', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Text;
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function isFilterable(): bool
    {
        return false;
    }

    public function sanitize(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('absint', $value);
        }

        return [];
    }
}
