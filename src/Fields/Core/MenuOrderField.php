<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class MenuOrderField extends AbstractField
{
    public function getKey(): string
    {
        return 'menu_order';
    }

    public function getLabel(): string
    {
        return __('Menu Order', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Integer;
    }

    public function sanitize(mixed $value): int
    {
        return (int) $value;
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if (! is_numeric($value) && $value !== '' && $value !== null) {
            return __('Menu order must be a number.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
