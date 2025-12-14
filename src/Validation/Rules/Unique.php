<?php

declare(strict_types=1);

namespace Luminor\Validation\Rules;

use Luminor\Validation\Rule;
use Luminor\Database\ConnectionInterface;

/**
 * Unique Rule
 *
 * Validates that a value is unique in a database table.
 */
final class Unique implements Rule
{
    private ConnectionInterface $connection;
    private string $table;
    private string $column;
    private ?int $except;

    public function __construct(
        ConnectionInterface $connection,
        string $table,
        string $column = 'id',
        ?int $except = null
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->column = $column;
        $this->except = $except;
    }

    /**
     * @inheritDoc
     */
    public function passes(string $attribute, mixed $value): bool
    {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE {$this->column} = ?";
        $bindings = [$value];

        if ($this->except !== null) {
            $query .= " AND id != ?";
            $bindings[] = $this->except;
        }

        $stmt = $this->connection->query($query, $bindings);
        $count = (int) $stmt->fetchColumn();

        return $count === 0;
    }

    /**
     * @inheritDoc
     */
    public function message(): string
    {
        return "The value has already been taken.";
    }
}
