<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Abstractions;

/**
 * Base class for the Specification pattern.
 *
 * Specifications encapsulate business rules that can be combined and reused.
 * They provide a way to express complex business logic as first-class objects.
 *
 * @template T
 */
abstract class Specification
{
    /**
     * Check if the candidate satisfies this specification.
     *
     * @param T $candidate The object to check
     */
    abstract public function isSatisfiedBy(mixed $candidate): bool;

    /**
     * Create a new specification that is satisfied when both this specification
     * and the other specification are satisfied (AND).
     *
     * @param Specification<T> $other
     *
     * @return Specification<T>
     */
    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    /**
     * Create a new specification that is satisfied when either this specification
     * or the other specification is satisfied (OR).
     *
     * @param Specification<T> $other
     *
     * @return Specification<T>
     */
    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    /**
     * Create a new specification that is satisfied when this specification
     * is NOT satisfied (NOT).
     *
     * @return Specification<T>
     */
    public function not(): Specification
    {
        return new NotSpecification($this);
    }
}

/**
 * Specification that combines two specifications with AND logic.
 *
 * @template T
 *
 * @extends Specification<T>
 */
final class AndSpecification extends Specification
{
    /**
     * @param Specification<T> $left
     * @param Specification<T> $right
     */
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) && $this->right->isSatisfiedBy($candidate);
    }
}

/**
 * Specification that combines two specifications with OR logic.
 *
 * @template T
 *
 * @extends Specification<T>
 */
final class OrSpecification extends Specification
{
    /**
     * @param Specification<T> $left
     * @param Specification<T> $right
     */
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) || $this->right->isSatisfiedBy($candidate);
    }
}

/**
 * Specification that negates another specification.
 *
 * @template T
 *
 * @extends Specification<T>
 */
final class NotSpecification extends Specification
{
    /**
     * @param Specification<T> $specification
     */
    public function __construct(
        private readonly Specification $specification,
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return ! $this->specification->isSatisfiedBy($candidate);
    }
}
