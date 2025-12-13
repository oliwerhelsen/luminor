<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Services;

use Luminor\DDD\Application\DTO\DataTransferObject;
use Luminor\DDD\Application\DTO\Mapper;
use Luminor\DDD\Application\DTO\PagedResult;
use Luminor\DDD\Domain\Abstractions\AggregateRoot;
use Luminor\DDD\Domain\Repository\Criteria;
use Luminor\DDD\Domain\Repository\Pagination;
use Luminor\DDD\Domain\Repository\RepositoryInterface;

/**
 * Base class for CRUD application services.
 *
 * Provides standard Create, Read, Update, Delete operations
 * with automatic DTO mapping and event publishing.
 *
 * @template TEntity of AggregateRoot
 * @template TDto of DataTransferObject
 * @template TCreateDto of DataTransferObject
 * @template TUpdateDto of DataTransferObject
 */
abstract class CrudApplicationService extends ApplicationService
{
    /**
     * @param RepositoryInterface<TEntity> $repository
     * @param Mapper<TEntity, TDto> $mapper
     */
    public function __construct(
        protected readonly RepositoryInterface $repository,
        protected readonly Mapper $mapper
    ) {
    }

    /**
     * Get an entity by its ID.
     *
     * @param mixed $id
     * @return TDto|null
     */
    public function getById(mixed $id): ?DataTransferObject
    {
        $entity = $this->repository->findById($id);

        if ($entity === null) {
            return null;
        }

        return $this->mapper->toDto($entity);
    }

    /**
     * Get an entity by its ID or throw an exception.
     *
     * @param mixed $id
     * @return TDto
     */
    public function getByIdOrFail(mixed $id): DataTransferObject
    {
        $entity = $this->repository->findByIdOrFail($id);
        return $this->mapper->toDto($entity);
    }

    /**
     * Get all entities.
     *
     * @return array<int, TDto>
     */
    public function getAll(): array
    {
        $entities = $this->repository->findAll();
        return $this->mapper->toDtoList($entities);
    }

    /**
     * Get entities matching criteria.
     *
     * @return array<int, TDto>
     */
    public function getByCriteria(Criteria $criteria): array
    {
        $entities = $this->repository->findByCriteria($criteria);
        return $this->mapper->toDtoList($entities);
    }

    /**
     * Get a paginated list of entities.
     *
     * @return PagedResult<TDto>
     */
    public function getPaginated(int $page = 1, int $perPage = Pagination::DEFAULT_PER_PAGE): PagedResult
    {
        $pagination = Pagination::create($page, $perPage);
        $criteria = Criteria::create()->paginate($pagination);

        $entities = $this->repository->findByCriteria($criteria);
        $totalCount = $this->repository->count();

        $pagedEntities = PagedResult::fromItems($entities, $totalCount, $page, $perPage);
        return $this->mapper->toPagedDto($pagedEntities);
    }

    /**
     * Get a paginated list of entities matching criteria.
     *
     * @return PagedResult<TDto>
     */
    public function getPaginatedByCriteria(
        Criteria $criteria,
        int $page = 1,
        int $perPage = Pagination::DEFAULT_PER_PAGE
    ): PagedResult {
        $pagination = Pagination::create($page, $perPage);
        $paginatedCriteria = $criteria->paginate($pagination);

        $entities = $this->repository->findByCriteria($paginatedCriteria);
        $totalCount = $this->repository->countByCriteria($criteria);

        $pagedEntities = PagedResult::fromItems($entities, $totalCount, $page, $perPage);
        return $this->mapper->toPagedDto($pagedEntities);
    }

    /**
     * Create a new entity.
     *
     * Override this method to implement creation logic.
     *
     * @param TCreateDto $dto
     * @return TDto
     */
    public function create(DataTransferObject $dto): DataTransferObject
    {
        $entity = $this->createEntity($dto);

        $this->repository->add($entity);
        $this->eventPublisher?->collectEvents($entity);
        $this->publishEvents();

        return $this->mapper->toDto($entity);
    }

    /**
     * Update an existing entity.
     *
     * Override this method to implement update logic.
     *
     * @param mixed $id
     * @param TUpdateDto $dto
     * @return TDto
     */
    public function update(mixed $id, DataTransferObject $dto): DataTransferObject
    {
        $entity = $this->repository->findByIdOrFail($id);
        $updatedEntity = $this->updateEntity($entity, $dto);

        $this->repository->update($updatedEntity);
        $this->eventPublisher?->collectEvents($updatedEntity);
        $this->publishEvents();

        return $this->mapper->toDto($updatedEntity);
    }

    /**
     * Delete an entity by its ID.
     *
     * @param mixed $id
     */
    public function delete(mixed $id): void
    {
        $entity = $this->repository->findByIdOrFail($id);

        $this->beforeDelete($entity);
        $this->repository->remove($entity);
        $this->eventPublisher?->collectEvents($entity);
        $this->publishEvents();
    }

    /**
     * Check if an entity exists.
     *
     * @param mixed $id
     */
    public function exists(mixed $id): bool
    {
        return $this->repository->exists($id);
    }

    /**
     * Count all entities.
     */
    public function count(): int
    {
        return $this->repository->count();
    }

    /**
     * Count entities matching criteria.
     */
    public function countByCriteria(Criteria $criteria): int
    {
        return $this->repository->countByCriteria($criteria);
    }

    /**
     * Create an entity from a DTO.
     *
     * Override this method to implement entity creation logic.
     *
     * @param TCreateDto $dto
     * @return TEntity
     */
    abstract protected function createEntity(DataTransferObject $dto): AggregateRoot;

    /**
     * Update an entity with data from a DTO.
     *
     * Override this method to implement entity update logic.
     *
     * @param TEntity $entity
     * @param TUpdateDto $dto
     * @return TEntity
     */
    abstract protected function updateEntity(AggregateRoot $entity, DataTransferObject $dto): AggregateRoot;

    /**
     * Hook called before deleting an entity.
     *
     * Override to add custom pre-delete logic.
     *
     * @param TEntity $entity
     */
    protected function beforeDelete(AggregateRoot $entity): void
    {
        // Override in subclass if needed
    }
}
