<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Schema;

/**
 * MySQL Schema Grammar
 */
final class MySqlGrammar extends SchemaGrammar
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
                $columns[] = "UNIQUE KEY {$this->wrap($index['name'])} ({$columnList})";
            } else {
                $columns[] = "KEY {$this->wrap($index['name'])} ({$columnList})";
            }
        }

        $columnsSql = implode(', ', $columns);

        return "CREATE TABLE {$table} ({$columnsSql}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
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
        return "SELECT * FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'";
    }

    /**
     * @inheritDoc
     */
    public function compileRename(string $from, string $to): string
    {
        return "RENAME TABLE {$this->wrap($from)} TO {$this->wrap($to)}";
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
        return $column->isUnsigned() ? 'INT UNSIGNED' : 'INT';
    }

    protected function typeBigInteger(Column $column): string
    {
        return $column->isUnsigned() ? 'BIGINT UNSIGNED' : 'BIGINT';
    }

    protected function typeBoolean(Column $column): string
    {
        return 'TINYINT(1)';
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
        return 'DATETIME';
    }

    protected function typeTimestamp(Column $column): string
    {
        return 'TIMESTAMP';
    }

    protected function typeJson(Column $column): string
    {
        return 'JSON';
    }
}
