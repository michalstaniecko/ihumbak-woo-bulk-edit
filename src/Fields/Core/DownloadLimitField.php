<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class DownloadLimitField extends AbstractField
{
    public function getKey(): string
    {
        return 'download_limit';
    }

    public function getLabel(): string
    {
        return __('Download Limit', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Integer;
    }

    public function sanitize(mixed $value): int
    {
        if ($value === '' || $value === null) {
            return -1; // WooCommerce uses -1 for unlimited.
        }

        return (int) $value;
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (! is_numeric($value)) {
            return __('Download limit must be a number.', 'ihumbak-woo-bulk-edit');
        }

        if ((int) $value < -1) {
            return __('Download limit must be -1 (unlimited) or a positive number.', 'ihumbak-woo-bulk-edit');
        }

        return true;
    }
}
