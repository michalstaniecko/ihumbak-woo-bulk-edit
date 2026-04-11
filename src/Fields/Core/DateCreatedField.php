<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class DateCreatedField extends AbstractField
{
    public function getKey(): string
    {
        return 'date_created';
    }

    public function getLabel(): string
    {
        return __('Date Created', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Date;
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function sanitize(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }
}
