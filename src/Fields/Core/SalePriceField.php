<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class SalePriceField extends AbstractField
{
    public function getKey(): string
    {
        return 'sale_price';
    }

    public function getLabel(): string
    {
        return __('Sale Price', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Price;
    }

    public function sanitize(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        return wc_format_decimal((string) $value);
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            return __('Sale price must be a non-negative number.', 'ihumbak-woo-bulk-edit');
        }

        $regularPrice = $context['regular_price'] ?? null;

        if ($regularPrice !== null && $regularPrice !== '' && (float) $value >= (float) $regularPrice) {
            return __('Sale price must be lower than regular price.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
