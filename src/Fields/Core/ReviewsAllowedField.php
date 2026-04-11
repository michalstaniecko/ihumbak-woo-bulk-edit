<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ReviewsAllowedField extends AbstractField
{
    public function getKey(): string
    {
        return 'reviews_allowed';
    }

    public function getLabel(): string
    {
        return __('Reviews Allowed', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Boolean;
    }

    public function getOptions(): array
    {
        return [
            'yes' => __('Yes', 'ihumbak-woo-bulk-edit'),
            'no'  => __('No', 'ihumbak-woo-bulk-edit'),
        ];
    }

    public function sanitize(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
