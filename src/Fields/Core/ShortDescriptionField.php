<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ShortDescriptionField extends AbstractField
{
    public function getKey(): string
    {
        return 'short_description';
    }

    public function getLabel(): string
    {
        return __('Short Description', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Textarea;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function sanitize(mixed $value): string
    {
        return wp_kses_post((string) $value);
    }
}
