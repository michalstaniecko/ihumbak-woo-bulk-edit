<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class SlugField extends AbstractField
{
    public function getKey(): string
    {
        return 'slug';
    }

    public function getLabel(): string
    {
        return __('Slug', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Text;
    }

    public function sanitize(mixed $value): string
    {
        return sanitize_title((string) $value);
    }
}
