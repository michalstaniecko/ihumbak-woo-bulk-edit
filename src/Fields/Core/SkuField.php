<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class SkuField extends AbstractField
{
    public function getKey(): string
    {
        return 'sku';
    }

    public function getLabel(): string
    {
        return __('SKU', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Text;
    }

    public function sanitize(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }
}
