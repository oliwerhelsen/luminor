<?php

declare(strict_types=1);

namespace Luminor\DDD\Validation\Rules;

use Luminor\DDD\Database\ConnectionInterface;
use Luminor\DDD\Validation\Rule;

/**
 * Exists Rule
 *
 * Validates that a value exists in a database table.
 */
final class Exists implements Rule
{
    private ConnectionInterface $connection;

    private string $table;

    private string $column;

    public function __construct(
        ConnectionInterface $connection,
        string $table,
        string $column = 'id',
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * @inheritDoc
     */
    public function passes(string $attribute, mixed $value): bool
    {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE {$this->column} = ?";
        $stmt = $this->connection->query($query, [$value]);
        $count = (int) $stmt->fetchColumn();

        return $count > 0;
    }

    /**
     * @inheritDoc
     */
    public function message(): string
    {
        return 'The selected value is invalid.';
    }
}
