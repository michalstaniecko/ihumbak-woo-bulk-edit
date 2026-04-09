<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class StockQuantityField extends AbstractField
{
    public function getKey(): string
    {
        return 'stock_quantity';
    }

    public function getLabel(): string
    {
        return __('Stock Quantity', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Integer;
    }

    public function sanitize(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (! is_numeric($value)) {
            return __('Stock quantity must be a number.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
