<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Schema;

use RuntimeException;

/**
 * PostgreSQL Schema Grammar
 */
final class PostgresGrammar extends SchemaGrammar
{
    /**
     * @inheritDoc
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $this->wrap($blueprint->getTable());
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->wrap($column->getName()) . ' ' . $this->getColumnSql($column);
        }

        // Add primary key
        if ($primaryKey = $blueprint->getPrimaryKey()) {
            $columns[] = "PRIMARY KEY ({$this->wrap($primaryKey)})";
        }

        $columnsSql = implode(', ', $columns);
        $sql = "CREATE TABLE {$table} ({$columnsSql})";

        // Add indexes separately for PostgreSQL
        foreach ($blueprint->getIndexes() as $index) {
            $columnList = implode(', ', array_map([$this, 'wrap'], $index['columns']));
            if ($index['type'] === 'unique') {
                $sql .= "; CREATE UNIQUE INDEX {$this->wrap($index['name'])} ON {$table} ({$columnList})";
            } else {
                $sql .= "; CREATE INDEX {$this->wrap($index['name'])} ON {$table} ({$columnList})";
            }
        }

        return $sql;
    }

    /**
     * @inheritDoc
     */
    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->wrap($table)}";
    }

    /**
     * @inheritDoc
     */
    public function compileTableExists(string $table): string
    {
        return "SELECT * FROM information_schema.tables WHERE table_name = '{$table}'";
    }

    /**
     * @inheritDoc
     */
    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE {$this->wrap($from)} RENAME TO {$this->wrap($to)}";
    }

    protected function typeString(Column $column): string
    {
        $length = $column->getAttributes()['length'] ?? 255;

        return "VARCHAR({$length})";
    }

    protected function typeText(Column $column): string
    {
        return 'TEXT';
    }

    protected function typeInteger(Column $column): string
    {
        return 'INTEGER';
    }

    protected function typeBigInteger(Column $column): string
    {
        return 'BIGINT';
    }

    protected function typeBoolean(Column $column): string
    {
        return 'BOOLEAN';
    }

    protected function typeDecimal(Column $column): string
    {
        $precision = $column->getAttributes()['precision'] ?? 8;
        $scale = $column->getAttributes()['scale'] ?? 2;

        return "DECIMAL({$precision}, {$scale})";
    }

    protected function typeDate(Column $column): string
    {
        return 'DATE';
    }

    protected function typeDatetime(Column $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    protected function typeTimestamp(Column $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    protected function typeJson(Column $column): string
    {
        return 'JSON';
    }

    protected function wrap(string $value): string
    {
        return "\"{$value}\"";
    }

    protected function getColumnSql(Column $column): string
    {
        $type = $column->getType();
        $method = 'type' . ucfirst($type);

        if (! method_exists($this, $method)) {
            throw new RuntimeException("Unknown column type: {$type}");
        }

        $sql = $this->$method($column);

        // Handle auto-increment for PostgreSQL
        if ($column->isAutoIncrement()) {
            if ($type === 'bigInteger') {
                return 'BIGSERIAL';
            }

            return 'SERIAL';
        }

        // Add modifiers
        if (! $column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault()) {
            $default = $column->getDefault();
            if (is_string($default)) {
                $sql .= " DEFAULT '{$default}'";
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? 'TRUE' : 'FALSE');
            } elseif (is_null($default)) {
                $sql .= ' DEFAULT NULL';
            } else {
                $sql .= " DEFAULT {$default}";
            }
        }

        return $sql;
    }
}
