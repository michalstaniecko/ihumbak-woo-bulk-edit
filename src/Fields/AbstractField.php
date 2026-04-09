<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

/**
 * Base implementation for common field behavior.
 */
abstract class AbstractField implements FieldInterface
{
    public function isEditable(): bool
    {
        return true;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function isFilterable(): bool
    {
        return true;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function validate(mixed $value, array $context = []): true|string
    {
        return true;
    }

    public function toArray(): array
    {
        return [
            'key'        => $this->getKey(),
            'label'      => $this->getLabel(),
            'type'       => $this->getType()->value,
            'editable'   => $this->isEditable(),
            'sortable'   => $this->isSortable(),
            'filterable' => $this->isFilterable(),
            'options'    => $this->getOptions(),
        ];
    }
}
