<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class BackordersField extends AbstractField
{
    private const ALLOWED = [
        'no'     => 'Do not allow',
        'notify' => 'Allow, but notify customer',
        'yes'    => 'Allow',
    ];

    public function getKey(): string
    {
        return 'backorders';
    }

    public function getLabel(): string
    {
        return __('Backorders', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Select;
    }

    public function getOptions(): array
    {
        return self::ALLOWED;
    }

    public function sanitize(mixed $value): string
    {
        $value = sanitize_text_field((string) $value);

        return isset(self::ALLOWED[$value]) ? $value : 'no';
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if (! isset(self::ALLOWED[(string) $value])) {
            return sprintf(
                __('Invalid backorders value "%s". Allowed: %s', 'ihumbak-woo-bulk-edit'),
                $value,
                implode(', ', array_keys(self::ALLOWED))
            );
        }

        return true;
    }
}
