<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
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
 * Base Doctrine repository implementation.
 *
 * Provides a foundation for repositories using Doctrine ORM.
 *
 * @template T of AggregateRoot
 *
 * @implements RepositoryInterface<T>
 */
abstract class DoctrineRepository implements RepositoryInterface
{
    protected EntityRepository $entityRepository;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        string $entityClass,
    ) {
        $this->entityRepository = $entityManager->getRepository($entityClass);
    }

    /**
     * Get the entity class name.
     *
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * @inheritDoc
     */
    public function findById(mixed $id): ?AggregateRoot
    {
        return $this->entityRepository->find($id);
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
        return $this->entityRepository->findAll();
    }

    /**
     * @inheritDoc
     */
    public function findByCriteria(Criteria $criteria): array
    {
        $qb = $this->createQueryBuilder('e');
        $this->applyCriteria($qb, $criteria);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function countByCriteria(Criteria $criteria): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e)');

        if ($criteria->hasFilter()) {
            $this->applyFilter($qb, $criteria->getFilter(), 'e');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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
        $this->entityManager->persist($aggregate);
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function update(AggregateRoot $aggregate): void
    {
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function remove(AggregateRoot $aggregate): void
    {
        $this->entityManager->remove($aggregate);
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function removeById(mixed $id): void
    {
        $aggregate = $this->findByIdOrFail($id);
        $this->remove($aggregate);
    }

    /**
     * Create a query builder for this entity.
     */
    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityRepository->createQueryBuilder($alias);
    }

    /**
     * Apply criteria to a query builder.
     */
    protected function applyCriteria(QueryBuilder $qb, Criteria $criteria): void
    {
        $alias = $qb->getRootAliases()[0];

        // Apply filters
        if ($criteria->hasFilter()) {
            $this->applyFilter($qb, $criteria->getFilter(), $alias);
        }

        // Apply sorting
        if ($criteria->hasSorting()) {
            foreach ($criteria->getSorting()->getOrders() as $order) {
                $qb->addOrderBy("{$alias}.{$order['field']}", $order['direction']);
            }
        }

        // Apply pagination
        if ($criteria->hasPagination()) {
            $pagination = $criteria->getPagination();
            $qb->setFirstResult($pagination->getOffset());
            $qb->setMaxResults($pagination->getLimit());
        }
    }

    /**
     * Apply a filter to the query builder.
     */
    protected function applyFilter(QueryBuilder $qb, Filter $filter, string $alias): void
    {
        $paramIndex = 0;
        $expr = $this->buildFilterExpression($qb, $filter, $alias, $paramIndex);

        if ($expr !== null) {
            $qb->andWhere($expr);
        }
    }

    /**
     * Build a Doctrine expression from a filter.
     */
    private function buildFilterExpression(
        QueryBuilder $qb,
        Filter $filter,
        string $alias,
        int &$paramIndex,
    ): ?string {
        if ($filter instanceof AndFilter) {
            $left = $this->buildFilterExpression($qb, $filter->getLeft(), $alias, $paramIndex);
            $right = $this->buildFilterExpression($qb, $filter->getRight(), $alias, $paramIndex);

            if ($left === null) {
                return $right;
            }
            if ($right === null) {
                return $left;
            }

            return "({$left} AND {$right})";
        }

        if ($filter instanceof OrFilter) {
            $left = $this->buildFilterExpression($qb, $filter->getLeft(), $alias, $paramIndex);
            $right = $this->buildFilterExpression($qb, $filter->getRight(), $alias, $paramIndex);

            if ($left === null) {
                return $right;
            }
            if ($right === null) {
                return $left;
            }

            return "({$left} OR {$right})";
        }

        $field = "{$alias}.{$filter->getField()}";
        $paramName = "param_{$paramIndex}";
        $paramIndex++;

        if ($filter instanceof EqualsFilter) {
            $qb->setParameter($paramName, $filter->getValue());

            return "{$field} = :{$paramName}";
        }

        if ($filter instanceof NotEqualsFilter) {
            $qb->setParameter($paramName, $filter->getValue());

            return "{$field} != :{$paramName}";
        }

        if ($filter instanceof ContainsFilter) {
            $qb->setParameter($paramName, '%' . $filter->getValue() . '%');

            return $filter->isCaseSensitive()
                ? "{$field} LIKE :{$paramName}"
                : "LOWER({$field}) LIKE LOWER(:{$paramName})";
        }

        if ($filter instanceof InFilter) {
            $qb->setParameter($paramName, $filter->getValue());

            return "{$field} IN (:{$paramName})";
        }

        if ($filter instanceof IsNullFilter) {
            return $filter->isNull()
                ? "{$field} IS NULL"
                : "{$field} IS NOT NULL";
        }

        if ($filter instanceof ComparisonFilter) {
            $qb->setParameter($paramName, $filter->getValue());
            $operator = match ($filter->getOperator()) {
                ComparisonFilter::GREATER_THAN => '>',
                ComparisonFilter::GREATER_THAN_OR_EQUAL => '>=',
                ComparisonFilter::LESS_THAN => '<',
                ComparisonFilter::LESS_THAN_OR_EQUAL => '<=',
                default => '=',
            };

            return "{$field} {$operator} :{$paramName}";
        }

        return null;
    }
}
