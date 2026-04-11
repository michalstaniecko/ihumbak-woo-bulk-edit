<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class ExternalUrlField extends AbstractField
{
    public function getKey(): string
    {
        return 'external_url';
    }

    public function getLabel(): string
    {
        return __('External URL', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Text;
    }

    public function sanitize(mixed $value): string
    {
        return esc_url_raw((string) $value);
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return __('External URL must be a valid URL.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
