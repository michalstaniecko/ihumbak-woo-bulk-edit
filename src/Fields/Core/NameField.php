<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class NameField extends AbstractField
{
    public function getKey(): string
    {
        return 'name';
    }

    public function getLabel(): string
    {
        return __('Name', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Text;
    }

    public function sanitize(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if (trim((string) $value) === '') {
            return __('Product name cannot be empty.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
