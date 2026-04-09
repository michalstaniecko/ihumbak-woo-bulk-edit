<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Registry of available filter operators.
 */
final class OperatorRegistry
{
    /** @var array<string, OperatorInterface> */
    private array $operators = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    public function register(OperatorInterface $operator): void
    {
        $this->operators[$operator->getIdentifier()] = $operator;
    }

    public function get(string $identifier): ?OperatorInterface
    {
        return $this->operators[strtoupper($identifier)] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->operators[strtoupper($identifier)]);
    }

    private function registerDefaults(): void
    {
        $defaults = [
            new EqualOperator(),
            new NotEqualOperator(),
            new LikeOperator(),
            new NotLikeOperator(),
            new IsEmptyOperator(),
            new IsNotEmptyOperator(),
        ];

        foreach ($defaults as $operator) {
            $this->register($operator);
        }
    }
}
