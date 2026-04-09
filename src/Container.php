<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit;

use InvalidArgumentException;

/**
 * Simple dependency injection container.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Register a factory for the given class/interface.
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Resolve a shared instance (singleton per container).
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (! isset($this->factories[$id])) {
            throw new InvalidArgumentException(
                sprintf('No factory registered for "%s".', $id)
            );
        }

        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }

    /**
     * Check if a factory is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
