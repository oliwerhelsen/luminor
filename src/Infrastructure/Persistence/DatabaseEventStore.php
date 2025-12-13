<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence;

use DateTimeImmutable;
use Luminor\DDD\Database\ConnectionInterface;
use Luminor\DDD\Domain\Abstractions\DomainEvent;
use Luminor\DDD\Domain\Events\EventStoreInterface;
use Luminor\DDD\Domain\Events\StoredEvent;
use PDO;

/**
 * Database-backed event store implementation.
 *
 * Persists domain events to a relational database with support for
 * event sourcing patterns. Events are stored with their aggregate ID,
 * type, version, and serialized payload.
 */
final class DatabaseEventStore implements EventStoreInterface
{
    private const TABLE_NAME = 'domain_events';

    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    public function append(DomainEvent $event): void
    {
        $sql = sprintf(
            'INSERT INTO %s (event_id, event_type, aggregate_id, aggregate_type, version, payload, metadata, occurred_on, stored_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::TABLE_NAME
        );

        $this->connection->execute($sql, [
            $event->getEventId(),
            $event->getEventType(),
            $event->getAggregateId(),
            $this->extractAggregateType($event),
            $this->getNextVersionForAggregate($event->getAggregateId() ?? ''),
            json_encode($event->getPayload()),
            json_encode($event->getMetadata()),
            $event->getOccurredOn()->format('Y-m-d H:i:s'),
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function appendAll(array $events): void
    {
        $this->connection->beginTransaction();

        try {
            foreach ($events as $event) {
                $this->append($event);
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function getEventsForAggregate(string $aggregateId): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_id = ? ORDER BY version ASC',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$aggregateId]);
        return $this->reconstructEvents($rows);
    }

    public function getEventsForAggregateFromVersion(string $aggregateId, int $fromVersion): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_id = ? AND version > ? ORDER BY version ASC',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$aggregateId, $fromVersion]);
        return $this->reconstructEvents($rows);
    }

    public function getEventsByType(string $eventClass): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE event_type = ? ORDER BY stored_at ASC',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$eventClass]);
        return $this->reconstructEvents($rows);
    }

    public function getEventsAfter(DateTimeImmutable $date): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE occurred_on > ? ORDER BY occurred_on ASC',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$date->format('Y-m-d H:i:s')]);
        return $this->reconstructEvents($rows);
    }

    public function getEventsBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE occurred_on >= ? AND occurred_on <= ? ORDER BY occurred_on ASC',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);

        return $this->reconstructEvents($rows);
    }

    public function getAggregateVersion(string $aggregateId): int
    {
        $sql = sprintf(
            'SELECT MAX(version) as max_version FROM %s WHERE aggregate_id = ?',
            self::TABLE_NAME
        );

        $result = $this->connection->query($sql, [$aggregateId]);

        if (empty($result)) {
            return 0;
        }

        return (int) ($result[0]['max_version'] ?? 0);
    }

    public function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) as total FROM %s', self::TABLE_NAME);
        $result = $this->connection->query($sql);

        return (int) ($result[0]['total'] ?? 0);
    }

    public function countForAggregate(string $aggregateId): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as total FROM %s WHERE aggregate_id = ?',
            self::TABLE_NAME
        );

        $result = $this->connection->query($sql, [$aggregateId]);

        return (int) ($result[0]['total'] ?? 0);
    }

    /**
     * Get all events in the store.
     *
     * @param int $limit Maximum number of events to return
     * @param int $offset Number of events to skip
     * @return array<int, DomainEvent>
     */
    public function getAllEvents(int $limit = 100, int $offset = 0): array
    {
        $sql = sprintf(
            'SELECT * FROM %s ORDER BY stored_at ASC LIMIT ? OFFSET ?',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$limit, $offset]);
        return $this->reconstructEvents($rows);
    }

    /**
     * Reconstruct domain events from database rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, DomainEvent>
     */
    private function reconstructEvents(array $rows): array
    {
        $events = [];

        foreach ($rows as $row) {
            $eventClass = $row['event_type'];

            if (!class_exists($eventClass)) {
                continue;
            }

            $payload = json_decode($row['payload'], true) ?? [];
            $metadata = json_decode($row['metadata'], true) ?? [];

            // Reconstruct the event using its fromArray method if available
            if (method_exists($eventClass, 'fromArray')) {
                $events[] = $eventClass::fromArray(array_merge($payload, [
                    'eventId' => $row['event_id'],
                    'occurredOn' => $row['occurred_on'],
                    'metadata' => $metadata,
                ]));
            }
        }

        return $events;
    }

    /**
     * Get the next version number for an aggregate.
     */
    private function getNextVersionForAggregate(string $aggregateId): int
    {
        if (empty($aggregateId)) {
            return 1;
        }

        return $this->getAggregateVersion($aggregateId) + 1;
    }

    /**
     * Extract aggregate type from event.
     */
    private function extractAggregateType(DomainEvent $event): ?string
    {
        if (method_exists($event, 'getAggregateType')) {
            return $event->getAggregateType();
        }

        return null;
    }
}
