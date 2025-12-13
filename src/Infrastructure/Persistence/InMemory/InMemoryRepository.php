<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence\InMemory;

use Luminor\DDD\Domain\Abstractions\AggregateRoot;
use Luminor\DDD\Domain\Repository\AggregateNotFoundException;
use Luminor\DDD\Domain\Repository\Criteria;
use Luminor\DDD\Domain\Repository\Filter\AndFilter;
use Luminor\DDD\Domain\Repository\Filter\ComparisonFilter;
use Luminor\DDD\Domain\Repository\Filter\ContainsFilter;
use Luminor\DDD\Domain\Repository\Filter\EqualsFilter;
use Luminor\DDD\Domain\Repository\Filter\Filter;
use Luminor\DDD\Domain\Repository\Filter\InFilter;
use Luminor\DDD\Domain\Repository\Filter\IsNullFilter;
use Luminor\DDD\Domain\Repository\Filter\NotEqualsFilter;
use Luminor\DDD\Domain\Repository\Filter\OrFilter;
use Luminor\DDD\Domain\Repository\RepositoryInterface;

/**
 * In-memory repository implementation for testing.
 *
 * Stores aggregates in memory, useful for unit tests and prototyping.
 *
 * @template T of AggregateRoot
 * @implements RepositoryInterface<T>
 */
class InMemoryRepository implements RepositoryInterface
{
    /** @var array<string, T> */
    protected array $entities = [];

    /**
     * Get the entity class name.
     *
     * @return class-string<T>
     */
    protected function getEntityClass(): string
    {
        return AggregateRoot::class;
    }

    /**
     * @inheritDoc
     */
    public function findById(mixed $id): ?AggregateRoot
    {
        $key = $this->getKey($id);
        return $this->entities[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function findByIdOrFail(mixed $id): AggregateRoot
    {
        $entity = $this->findById($id);

        if ($entity === null) {
            throw AggregateNotFoundException::withId($this->getEntityClass(), $id);
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        return array_values($this->entities);
    }

    /**
     * @inheritDoc
     */
    public function findByCriteria(Criteria $criteria): array
    {
        $results = $this->findAll();

        // Apply filter
        if ($criteria->hasFilter()) {
            $results = array_filter(
                $results,
                fn(AggregateRoot $entity) => $this->matchesFilter($entity, $criteria->getFilter())
            );
        }

        // Apply sorting
        if ($criteria->hasSorting()) {
            $orders = $criteria->getSorting()->getOrders();
            usort($results, function (AggregateRoot $a, AggregateRoot $b) use ($orders) {
                foreach ($orders as $order) {
                    $field = $order['field'];
                    $direction = $order['direction'];

                    $valueA = $this->getFieldValue($a, $field);
                    $valueB = $this->getFieldValue($b, $field);

                    $comparison = $valueA <=> $valueB;

                    if ($comparison !== 0) {
                        return $direction === 'DESC' ? -$comparison : $comparison;
                    }
                }
                return 0;
            });
        }

        // Apply pagination
        if ($criteria->hasPagination()) {
            $pagination = $criteria->getPagination();
            $results = array_slice(
                array_values($results),
                $pagination->getOffset(),
                $pagination->getLimit()
            );
        }

        return array_values($results);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->entities);
    }

    /**
     * @inheritDoc
     */
    public function countByCriteria(Criteria $criteria): int
    {
        if (!$criteria->hasFilter()) {
            return $this->count();
        }

        $count = 0;
        foreach ($this->entities as $entity) {
            if ($this->matchesFilter($entity, $criteria->getFilter())) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @inheritDoc
     */
    public function exists(mixed $id): bool
    {
        return $this->findById($id) !== null;
    }

    /**
     * @inheritDoc
     */
    public function add(AggregateRoot $aggregate): void
    {
        $key = $this->getKey($aggregate->getId());
        $this->entities[$key] = $aggregate;
    }

    /**
     * @inheritDoc
     */
    public function update(AggregateRoot $aggregate): void
    {
        $key = $this->getKey($aggregate->getId());
        $this->entities[$key] = $aggregate;
    }

    /**
     * @inheritDoc
     */
    public function remove(AggregateRoot $aggregate): void
    {
        $key = $this->getKey($aggregate->getId());
        unset($this->entities[$key]);
    }

    /**
     * @inheritDoc
     */
    public function removeById(mixed $id): void
    {
        $key = $this->getKey($id);
        unset($this->entities[$key]);
    }

    /**
     * Clear all entities (useful for testing).
     */
    public function clear(): void
    {
        $this->entities = [];
    }

    /**
     * Get the storage key for an ID.
     */
    protected function getKey(mixed $id): string
    {
        return (string) $id;
    }

    /**
     * Check if an entity matches a filter.
     */
    protected function matchesFilter(AggregateRoot $entity, Filter $filter): bool
    {
        if ($filter instanceof AndFilter) {
            return $this->matchesFilter($entity, $filter->getLeft())
                && $this->matchesFilter($entity, $filter->getRight());
        }

        if ($filter instanceof OrFilter) {
            return $this->matchesFilter($entity, $filter->getLeft())
                || $this->matchesFilter($entity, $filter->getRight());
        }

        $field = $filter->getField();
        $value = $this->getFieldValue($entity, $field);
        $filterValue = $filter->getValue();

        if ($filter instanceof EqualsFilter) {
            return $value === $filterValue;
        }

        if ($filter instanceof NotEqualsFilter) {
            return $value !== $filterValue;
        }

        if ($filter instanceof ContainsFilter) {
            if (!is_string($value)) {
                return false;
            }
            return $filter->isCaseSensitive()
                ? str_contains($value, $filterValue)
                : str_contains(strtolower($value), strtolower($filterValue));
        }

        if ($filter instanceof InFilter) {
            return in_array($value, $filterValue, true);
        }

        if ($filter instanceof IsNullFilter) {
            return $filter->isNull() ? $value === null : $value !== null;
        }

        if ($filter instanceof ComparisonFilter) {
            return match ($filter->getOperator()) {
                ComparisonFilter::GREATER_THAN => $value > $filterValue,
                ComparisonFilter::GREATER_THAN_OR_EQUAL => $value >= $filterValue,
                ComparisonFilter::LESS_THAN => $value < $filterValue,
                ComparisonFilter::LESS_THAN_OR_EQUAL => $value <= $filterValue,
                default => false,
            };
        }

        return true;
    }

    /**
     * Get a field value from an entity using reflection.
     */
    protected function getFieldValue(AggregateRoot $entity, string $field): mixed
    {
        // Try getter method
        $getter = 'get' . ucfirst($field);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }

        // Try direct property access
        $reflection = new \ReflectionClass($entity);
        if ($reflection->hasProperty($field)) {
            $property = $reflection->getProperty($field);
            return $property->getValue($entity);
        }

        return null;
    }
}
