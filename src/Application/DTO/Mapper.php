<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\DTO;

use Lumina\DDD\Domain\Abstractions\Entity;

/**
 * Maps between domain entities and DTOs.
 *
 * Provides utilities for converting domain objects to DTOs and vice versa.
 *
 * @template TEntity of Entity
 * @template TDto of DataTransferObject
 */
abstract class Mapper
{
    /**
     * Map a domain entity to a DTO.
     *
     * @param TEntity $entity
     * @return TDto
     */
    abstract public function toDto(Entity $entity): DataTransferObject;

    /**
     * Map a DTO to a domain entity.
     *
     * @param TDto $dto
     * @return TEntity
     */
    abstract public function toEntity(DataTransferObject $dto): Entity;

    /**
     * Map an array of entities to DTOs.
     *
     * @param array<int, TEntity> $entities
     * @return array<int, TDto>
     */
    public function toDtoList(array $entities): array
    {
        return array_map(fn(Entity $entity) => $this->toDto($entity), $entities);
    }

    /**
     * Map an array of DTOs to entities.
     *
     * @param array<int, TDto> $dtos
     * @return array<int, TEntity>
     */
    public function toEntityList(array $dtos): array
    {
        return array_map(fn(DataTransferObject $dto) => $this->toEntity($dto), $dtos);
    }

    /**
     * Map a paged result of entities to a paged result of DTOs.
     *
     * @param PagedResult<TEntity> $pagedResult
     * @return PagedResult<TDto>
     */
    public function toPagedDto(PagedResult $pagedResult): PagedResult
    {
        return $pagedResult->map(fn(Entity $entity) => $this->toDto($entity));
    }

    /**
     * Update an existing entity with data from a DTO.
     *
     * Override this method to implement update logic.
     *
     * @param TEntity $entity
     * @param TDto $dto
     * @return TEntity
     */
    public function updateEntity(Entity $entity, DataTransferObject $dto): Entity
    {
        throw new \BadMethodCallException(
            sprintf('Method %s::updateEntity() must be implemented to support entity updates', static::class)
        );
    }
}
