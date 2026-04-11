<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ButtonTextField extends AbstractField
{
    public function getKey(): string
    {
        return 'button_text';
    }

    public function getLabel(): string
    {
        return __('Button Text', 'ihumbak-woo-bulk-edit');
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
