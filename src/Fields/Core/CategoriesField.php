<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class CategoriesField extends AbstractField
{
    public function getKey(): string
    {
        return 'categories';
    }

    public function getLabel(): string
    {
        return __('Categories', 'ihumbak-woo-bulk-edit');
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

    public function sanitize(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('absint', $value);
        }

        return [];
    }
}
