<?php

declare(strict_types=1);

namespace Luminor\Database\Schema;

/**
 * Blueprint
 *
 * Fluent table builder for defining database schemas.
 */
final class Blueprint
{
    private string $table;
    /** @var array<Column> */
    private array $columns = [];
    /** @var array<string> */
    private array $indexes = [];
    private ?string $primaryKey = null;
    private bool $timestamps = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Get the table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Add an auto-incrementing ID column.
     */
    public function id(string $name = 'id'): Column
    {
        $column = $this->integer($name)->autoIncrement()->unsigned();
        $this->primaryKey = $name;
        return $column;
    }

    /**
     * Add a string column.
     */
    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn('string', $name, ['length' => $length]);
    }

    /**
     * Add a text column.
     */
    public function text(string $name): Column
    {
        return $this->addColumn('text', $name);
    }

    /**
     * Add an integer column.
     */
    public function integer(string $name): Column
    {
        return $this->addColumn('integer', $name);
    }

    /**
     * Add a big integer column.
     */
    public function bigInteger(string $name): Column
    {
        return $this->addColumn('bigInteger', $name);
    }

    /**
     * Add a boolean column.
     */
    public function boolean(string $name): Column
    {
        return $this->addColumn('boolean', $name);
    }

    /**
     * Add a decimal column.
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('decimal', $name, [
            'precision' => $precision,
            'scale' => $scale,
        ]);
    }

    /**
     * Add a date column.
     */
    public function date(string $name): Column
    {
        return $this->addColumn('date', $name);
    }

    /**
     * Add a datetime column.
     */
    public function datetime(string $name): Column
    {
        return $this->addColumn('datetime', $name);
    }

    /**
     * Add a timestamp column.
     */
    public function timestamp(string $name): Column
    {
        return $this->addColumn('timestamp', $name);
    }

    /**
     * Add created_at and updated_at timestamp columns.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        $this->timestamps = true;
    }

    /**
     * Add a JSON column.
     */
    public function json(string $name): Column
    {
        return $this->addColumn('json', $name);
    }

    /**
     * Add a foreign key column.
     */
    public function foreignId(string $name): Column
    {
        return $this->bigInteger($name)->unsigned();
    }

    /**
     * Add an index.
     */
    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_index';
        $this->indexes[] = ['type' => 'index', 'columns' => $columns, 'name' => $name];
        return $this;
    }

    /**
     * Add a unique index.
     */
    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[] = ['type' => 'unique', 'columns' => $columns, 'name' => $name];
        return $this;
    }

    /**
     * Get all columns.
     *
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get all indexes.
     *
     * @return array<array{type: string, columns: array<string>, name: string}>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Get the primary key column name.
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * Check if timestamps are enabled.
     */
    public function hasTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Add a column.
     *
     * @param array<string, mixed> $attributes
     */
    private function addColumn(string $type, string $name, array $attributes = []): Column
    {
        $column = new Column($type, $name, $attributes);
        $this->columns[] = $column;
        return $column;
    }
}
