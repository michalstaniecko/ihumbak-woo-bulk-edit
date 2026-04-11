<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class WidthField extends AbstractField
{
    public function getKey(): string
    {
        return 'width';
    }

    public function getLabel(): string
    {
        return __('Width', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Number;
    }

    public function sanitize(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        return wc_format_decimal($value);
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (! is_numeric($value)) {
            return __('Width must be a number.', 'ihumbak-woo-bulk-edit');
        }

        if ((float) $value < 0) {
            return __('Width cannot be negative.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
