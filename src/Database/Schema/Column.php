<?php

declare(strict_types=1);

namespace Lumina\DDD\Database\Schema;

/**
 * Column
 *
 * Represents a database column definition.
 */
final class Column
{
    private string $type;
    private string $name;
    /** @var array<string, mixed> */
    private array $attributes;
    private bool $nullable = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $unsigned = false;
    private bool $autoIncrement = false;
    private ?string $comment = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(string $type, string $name, array $attributes = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->attributes = $attributes;
    }

    /**
     * Get the column type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get column attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Mark the column as nullable.
     */
    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    /**
     * Check if the column is nullable.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Set a default value.
     */
    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Get the default value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Check if the column has a default value.
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Mark the column as unsigned.
     */
    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    /**
     * Check if the column is unsigned.
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * Mark the column as auto-incrementing.
     */
    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    /**
     * Check if the column is auto-incrementing.
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * Add a comment to the column.
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Get the column comment.
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }
}
