<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

use IhumbakWooBulkEdit\Fields\Core\NameField;
use IhumbakWooBulkEdit\Fields\Core\SkuField;
use IhumbakWooBulkEdit\Fields\Core\RegularPriceField;
use IhumbakWooBulkEdit\Fields\Core\SalePriceField;
use IhumbakWooBulkEdit\Fields\Core\StockQuantityField;
use IhumbakWooBulkEdit\Fields\Core\StatusField;

/**
 * Registry of all available product fields.
 */
final class FieldRegistry
{
    /** @var array<string, FieldInterface> */
    private array $fields = [];

    public function __construct()
    {
        $this->registerCoreFields();
    }

    public function register(FieldInterface $field): void
    {
        $this->fields[$field->getKey()] = $field;
    }

    public function get(string $key): ?FieldInterface
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getAll(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getEditable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isEditable());
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getFilterable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isFilterable());
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getSortable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isSortable());
    }

    /**
     * Convert all fields to array for REST API.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(
            array_map(
                static fn (FieldInterface $f): array => $f->toArray(),
                $this->fields
            )
        );
    }

    private function registerCoreFields(): void
    {
        $coreFields = [
            new NameField(),
            new SkuField(),
            new RegularPriceField(),
            new SalePriceField(),
            new StockQuantityField(),
            new StatusField(),
        ];

        foreach ($coreFields as $field) {
            $this->register($field);
        }
    }
}
