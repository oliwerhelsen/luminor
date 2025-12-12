<?php

declare(strict_types=1);

namespace Lumina\DDD\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Lumina\DDD\Domain\Abstractions\AggregateRoot;
use Lumina\DDD\Domain\Events\DomainEventPublisher;
use Lumina\DDD\Infrastructure\Persistence\UnitOfWorkInterface;

/**
 * Doctrine-based Unit of Work implementation.
 *
 * Wraps Doctrine's built-in unit of work and adds domain event publishing.
 */
final class DoctrineUnitOfWork implements UnitOfWorkInterface
{
    /** @var array<string, AggregateRoot> */
    private array $newAggregates = [];

    /** @var array<string, AggregateRoot> */
    private array $dirtyAggregates = [];

    /** @var array<string, AggregateRoot> */
    private array $removedAggregates = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?DomainEventPublisher $eventPublisher = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function registerNew(AggregateRoot $aggregate): void
    {
        $id = $this->getAggregateId($aggregate);
        $this->newAggregates[$id] = $aggregate;
        $this->entityManager->persist($aggregate);
    }

    /**
     * @inheritDoc
     */
    public function registerDirty(AggregateRoot $aggregate): void
    {
        $id = $this->getAggregateId($aggregate);

        if (!isset($this->newAggregates[$id])) {
            $this->dirtyAggregates[$id] = $aggregate;
        }
    }

    /**
     * @inheritDoc
     */
    public function registerRemoved(AggregateRoot $aggregate): void
    {
        $id = $this->getAggregateId($aggregate);

        unset($this->newAggregates[$id], $this->dirtyAggregates[$id]);

        $this->removedAggregates[$id] = $aggregate;
        $this->entityManager->remove($aggregate);
    }

    /**
     * @inheritDoc
     */
    public function registerClean(AggregateRoot $aggregate): void
    {
        $id = $this->getAggregateId($aggregate);

        unset(
            $this->newAggregates[$id],
            $this->dirtyAggregates[$id],
            $this->removedAggregates[$id]
        );
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        // Collect events from all aggregates before flush
        if ($this->eventPublisher !== null) {
            $this->collectEvents();
        }

        // Persist changes
        $this->entityManager->flush();

        // Publish events after successful commit
        $this->eventPublisher?->publishPending();

        // Clear tracking
        $this->clear();
    }

    /**
     * @inheritDoc
     */
    public function rollback(): void
    {
        // Clear Doctrine's unit of work
        $this->entityManager->clear();

        // Clear event publisher
        $this->eventPublisher?->clearPendingEvents();

        // Clear tracking
        $this->clear();
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->newAggregates = [];
        $this->dirtyAggregates = [];
        $this->removedAggregates = [];
    }

    /**
     * @inheritDoc
     */
    public function isRegistered(AggregateRoot $aggregate): bool
    {
        $id = $this->getAggregateId($aggregate);

        return isset($this->newAggregates[$id])
            || isset($this->dirtyAggregates[$id])
            || isset($this->removedAggregates[$id]);
    }

    /**
     * @inheritDoc
     */
    public function getPendingCount(): int
    {
        return count($this->newAggregates)
            + count($this->dirtyAggregates)
            + count($this->removedAggregates);
    }

    /**
     * Collect events from all tracked aggregates.
     */
    private function collectEvents(): void
    {
        $allAggregates = array_merge(
            array_values($this->newAggregates),
            array_values($this->dirtyAggregates),
            array_values($this->removedAggregates)
        );

        $this->eventPublisher->collectEventsFromAll($allAggregates);
    }

    /**
     * Get a unique identifier for an aggregate.
     */
    private function getAggregateId(AggregateRoot $aggregate): string
    {
        $id = $aggregate->getId();

        if ($id === null) {
            return spl_object_hash($aggregate);
        }

        return $aggregate::class . ':' . (string) $id;
    }
}
