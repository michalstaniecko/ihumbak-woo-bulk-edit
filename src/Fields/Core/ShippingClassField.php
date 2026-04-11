<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ShippingClassField extends AbstractField
{
    public function getKey(): string
    {
        return 'shipping_class';
    }

    public function getLabel(): string
    {
        return __('Shipping Class', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Taxonomy;
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function sanitize(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }
}
