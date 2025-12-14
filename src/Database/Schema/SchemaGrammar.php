<?php

declare(strict_types=1);

namespace Luminor\Database\Schema;

/**
 * Schema Grammar
 *
 * Abstract base class for database-specific schema grammars.
 */
abstract class SchemaGrammar
{
    /**
     * Compile a create table statement.
     */
    abstract public function compileCreate(Blueprint $blueprint): string;

    /**
     * Compile a drop table if exists statement.
     */
    abstract public function compileDropIfExists(string $table): string;

    /**
     * Compile a table exists check.
     */
    abstract public function compileTableExists(string $table): string;

    /**
     * Compile a rename table statement.
     */
    abstract public function compileRename(string $from, string $to): string;

    /**
     * Compile column type.
     */
    abstract protected function typeString(Column $column): string;
    abstract protected function typeText(Column $column): string;
    abstract protected function typeInteger(Column $column): string;
    abstract protected function typeBigInteger(Column $column): string;
    abstract protected function typeBoolean(Column $column): string;
    abstract protected function typeDecimal(Column $column): string;
    abstract protected function typeDate(Column $column): string;
    abstract protected function typeDatetime(Column $column): string;
    abstract protected function typeTimestamp(Column $column): string;
    abstract protected function typeJson(Column $column): string;

    /**
     * Get the SQL for a column.
     */
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
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }

    /**
     * Wrap a value in keyword identifiers.
     */
    protected function wrap(string $value): string
    {
        return "`{$value}`";
    }
}
