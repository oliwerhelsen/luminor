<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Schema;

/**
 * SQLite Schema Grammar
 */
final class SqliteGrammar extends SchemaGrammar
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

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            $columnList = implode(', ', array_map([$this, 'wrap'], $index['columns']));
            if ($index['type'] === 'unique') {
                $columns[] = "UNIQUE ({$columnList})";
            }
        }

        $columnsSql = implode(', ', $columns);
        $sql = "CREATE TABLE {$table} ({$columnsSql})";

        // Regular indexes need separate CREATE INDEX statements for SQLite
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'index') {
                $columnList = implode(', ', array_map([$this, 'wrap'], $index['columns']));
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
        return "SELECT * FROM sqlite_master WHERE type = 'table' AND name = '{$table}'";
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
        return 'TEXT';
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
        return 'INTEGER';
    }

    protected function typeBoolean(Column $column): string
    {
        return 'INTEGER';
    }

    protected function typeDecimal(Column $column): string
    {
        return 'REAL';
    }

    protected function typeDate(Column $column): string
    {
        return 'TEXT';
    }

    protected function typeDatetime(Column $column): string
    {
        return 'TEXT';
    }

    protected function typeTimestamp(Column $column): string
    {
        return 'TEXT';
    }

    protected function typeJson(Column $column): string
    {
        return 'TEXT';
    }

    protected function getColumnSql(Column $column): string
    {
        $type = $column->getType();
        $method = 'type' . ucfirst($type);

        if (!method_exists($this, $method)) {
            throw new \RuntimeException("Unknown column type: {$type}");
        }

        $sql = $this->$method($column);

        // Add modifiers
        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault()) {
            $default = $column->getDefault();
            if (is_string($default)) {
                $sql .= " DEFAULT '{$default}'";
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_null($default)) {
                $sql .= ' DEFAULT NULL';
            } else {
                $sql .= " DEFAULT {$default}";
            }
        }

        if ($column->isAutoIncrement()) {
            $sql .= ' AUTOINCREMENT';
        }

        return $sql;
    }
}
