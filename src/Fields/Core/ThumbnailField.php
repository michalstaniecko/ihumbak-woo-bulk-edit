<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ThumbnailField extends AbstractField
{
    public function getKey(): string
    {
        return 'thumbnail';
    }

    public function getLabel(): string
    {
        return __('Thumbnail', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Image;
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function isFilterable(): bool
    {
        return false;
    }

    public function sanitize(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return absint($value);
    }
}
