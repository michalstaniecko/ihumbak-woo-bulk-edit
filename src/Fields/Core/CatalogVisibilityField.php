<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

final class CatalogVisibilityField extends AbstractField
{
    private const VISIBILITY_OPTIONS = [
        'visible' => 'Shop and search results',
        'catalog' => 'Shop only',
        'search'  => 'Search results only',
        'hidden'  => 'Hidden',
    ];

    public function getKey(): string
    {
        return 'catalog_visibility';
    }

    public function getLabel(): string
    {
        return __('Catalog Visibility', 'ihumbak-woo-bulk-edit');
    }

    public function getType(): FieldType
    {
        return FieldType::Select;
    }

    public function getOptions(): array
    {
        return self::VISIBILITY_OPTIONS;
    }

    public function sanitize(mixed $value): string
    {
        $value = sanitize_text_field((string) $value);

        return isset(self::VISIBILITY_OPTIONS[$value]) ? $value : 'visible';
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        if (! isset(self::VISIBILITY_OPTIONS[(string) $value])) {
            return sprintf(
                __('Invalid catalog visibility "%s". Allowed: %s', 'ihumbak-woo-bulk-edit'),
                $value,
                implode(', ', array_keys(self::VISIBILITY_OPTIONS))
            );
        }

        return true;
    }
}
