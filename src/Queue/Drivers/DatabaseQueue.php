<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue\Drivers;

use DateTimeImmutable;
use Luminor\DDD\Queue\JobInterface;
use Luminor\DDD\Queue\QueuedJob;
use Luminor\DDD\Queue\QueueInterface;
use PDO;
use RuntimeException;

/**
 * Database-backed queue driver.
 *
 * Stores jobs in a database table. This is the default driver
 * that requires no external dependencies.
 *
 * Required table schema:
 * CREATE TABLE jobs (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     queue VARCHAR(255) NOT NULL,
 *     payload LONGTEXT NOT NULL,
 *     attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 *     reserved_at INT UNSIGNED NULL,
 *     available_at INT UNSIGNED NOT NULL,
 *     created_at INT UNSIGNED NOT NULL,
 *     INDEX jobs_queue_index (queue),
 *     INDEX jobs_available_at_index (available_at)
 * );
 */
final class DatabaseQueue implements QueueInterface
{
    private ?PDO $pdo = null;
    private string $table;
    private string $defaultQueue;
    private int $retryAfter;
    private string $connectionName;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Configuration options:
     *                                      - connection: PDO connection or DSN
     *                                      - table: Jobs table name (default: jobs)
     *                                      - queue: Default queue name (default: default)
     *                                      - retry_after: Seconds before a reserved job is released (default: 90)
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->table = $config['table'] ?? 'jobs';
        $this->defaultQueue = $config['queue'] ?? 'default';
        $this->retryAfter = $config['retry_after'] ?? 90;
        $this->connectionName = $config['connection_name'] ?? 'database';

        if (isset($config['pdo']) && $config['pdo'] instanceof PDO) {
            $this->pdo = $config['pdo'];
        }
    }

    /**
     * Get or create the PDO connection.
     */
    private function getPdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = $this->config['dsn'] ?? null;

        if ($dsn === null) {
            throw new RuntimeException(
                'Database queue requires a PDO instance or DSN. ' .
                'Set "pdo" or "dsn" in the queue configuration.'
            );
        }

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $options = $this->config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);

        return $this->pdo;
    }

    /**
     * @inheritDoc
     */
    public function push(JobInterface $job, ?string $queue = null): string|int
    {
        return $this->pushToDatabase($job, $queue, 0);
    }

    /**
     * @inheritDoc
     */
    public function later(JobInterface $job, int $delay, ?string $queue = null): string|int
    {
        return $this->pushToDatabase($job, $queue, $delay);
    }

    /**
     * Push a job to the database.
     */
    private function pushToDatabase(JobInterface $job, ?string $queue, int $delay): string|int
    {
        $queue = $queue ?? $this->defaultQueue;
        $payload = $this->createPayload($job);
        $availableAt = time() + $delay;

        $sql = sprintf(
            'INSERT INTO %s (queue, payload, attempts, available_at, created_at) VALUES (?, ?, 0, ?, ?)',
            $this->table
        );

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$queue, $payload, $availableAt, time()]);

        return $pdo->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function pop(?string $queue = null): ?QueuedJob
    {
        $queue = $queue ?? $this->defaultQueue;
        $pdo = $this->getPdo();

        // Start transaction for atomic pop
        $pdo->beginTransaction();

        try {
            // Find the next available job
            $sql = sprintf(
                'SELECT * FROM %s WHERE queue = ? AND available_at <= ? AND (reserved_at IS NULL OR reserved_at <= ?) ORDER BY id ASC LIMIT 1 FOR UPDATE',
                $this->table
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$queue, time(), time() - $this->retryAfter]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();
                return null;
            }

            // Reserve the job
            $updateSql = sprintf(
                'UPDATE %s SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?',
                $this->table
            );
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([time(), $row['id']]);

            $pdo->commit();

            return $this->createQueuedJob($row);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string|int $jobId, ?string $queue = null): void
    {
        $sql = sprintf('DELETE FROM %s WHERE id = ?', $this->table);
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$jobId]);
    }

    /**
     * @inheritDoc
     */
    public function release(QueuedJob $job, int $delay = 0): void
    {
        $sql = sprintf(
            'UPDATE %s SET reserved_at = NULL, available_at = ? WHERE id = ?',
            $this->table
        );

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([time() + $delay, $job->getId()]);
    }

    /**
     * @inheritDoc
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;

        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE queue = ?', $this->table);
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$queue]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function clear(?string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;

        $sql = sprintf('DELETE FROM %s WHERE queue = ?', $this->table);
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$queue]);

        return $stmt->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Create the JSON payload for a job.
     */
    private function createPayload(JobInterface $job): string
    {
        return json_encode($job->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create a QueuedJob from a database row.
     *
     * @param array<string, mixed> $row
     */
    private function createQueuedJob(array $row): QueuedJob
    {
        $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $jobClass = $payload['class'];

        if (!class_exists($jobClass)) {
            throw new RuntimeException(sprintf('Job class [%s] not found.', $jobClass));
        }

        if (!is_subclass_of($jobClass, JobInterface::class)) {
            throw new RuntimeException(sprintf('Job class [%s] must implement JobInterface.', $jobClass));
        }

        /** @var JobInterface $job */
        $job = $jobClass::fromArray($payload);

        return new QueuedJob(
            id: $row['id'],
            job: $job,
            queue: $row['queue'],
            attempts: (int) $row['attempts'],
            reservedAt: new DateTimeImmutable('@' . ($row['reserved_at'] ?? time())),
            rawPayload: $payload,
        );
    }

    /**
     * Create the jobs table.
     *
     * Helper method for migrations.
     */
    public function createTable(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                reserved_at INT UNSIGNED NULL,
                available_at INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                INDEX {$this->table}_queue_index (queue),
                INDEX {$this->table}_available_at_index (available_at)
            )
            SQL;

        $this->getPdo()->exec($sql);
    }

    /**
     * Create the failed jobs table.
     *
     * Helper method for migrations.
     */
    public function createFailedJobsTable(string $table = 'failed_jobs'): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                connection VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                exception LONGTEXT NOT NULL,
                failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL;

        $this->getPdo()->exec($sql);
    }
}
