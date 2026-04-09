<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

/**
 * Contract for a product field definition.
 */
interface FieldInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    public function getType(): FieldType;

    public function isEditable(): bool;

    public function isSortable(): bool;

    public function isFilterable(): bool;

    /**
     * Return select/enum options if applicable, empty array otherwise.
     *
     * @return array<string, string>
     */
    public function getOptions(): array;

    /**
     * Sanitize a raw value for this field.
     */
    public function sanitize(mixed $value): mixed;

    /**
     * Validate a value. Returns true on success, error message string on failure.
     *
     * @param array<string, mixed> $context Other field values for cross-field validation.
     */
    public function validate(mixed $value, array $context = []): true|string;

    /**
     * Convert to array for REST API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
