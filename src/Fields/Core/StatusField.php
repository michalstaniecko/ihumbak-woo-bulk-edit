<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class StatusField extends AbstractField
{
    private const STATUSES = [
        'publish' => 'Published',
        'draft'   => 'Draft',
        'pending' => 'Pending',
        'private' => 'Private',
        'trash'   => 'Trash',
    ];

    public function getKey(): string
    {
        return 'status';
    }

    public function getLabel(): string
    {
        return __('Status', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Select;
    }

    public function getOptions(): array
    {
        return self::STATUSES;
    }

    public function sanitize(mixed $value): string
    {
        $value = sanitize_text_field((string) $value);

        return isset(self::STATUSES[$value]) ? $value : 'draft';
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if (! isset(self::STATUSES[(string) $value])) {
            return sprintf(
                __('Invalid status "%s". Allowed: %s', 'ihumbak-woo-bulk-edit'),
                $value,
                implode(', ', array_keys(self::STATUSES))
            );
        }

        return true;
    }
}
