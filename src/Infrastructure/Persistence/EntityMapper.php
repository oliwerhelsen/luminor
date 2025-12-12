<?php

declare(strict_types=1);

namespace Lumina\DDD\Infrastructure\Persistence;

use Lumina\DDD\Domain\Abstractions\Entity;
use ReflectionClass;
use ReflectionProperty;

/**
 * Helper for mapping between entities and database rows.
 *
 * Provides utilities for hydrating entities from database results
 * and extracting data from entities for persistence.
 */
final class EntityMapper
{
    /**
     * Hydrate an entity from an array of data.
     *
     * @template T of Entity
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @return T
     */
    public static function hydrate(string $entityClass, array $data): Entity
    {
        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $field => $value) {
            $propertyName = self::toCamelCase($field);

            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setValue($entity, $value);
            }
        }

        return $entity;
    }

    /**
     * Extract data from an entity as an array.
     *
     * @param array<int, string>|null $fields Specific fields to extract (null for all)
     * @return array<string, mixed>
     */
    public static function extract(Entity $entity, ?array $fields = null): array
    {
        $reflection = new ReflectionClass($entity);
        $properties = $reflection->getProperties();
        $data = [];

        foreach ($properties as $property) {
            $name = $property->getName();

            if ($fields !== null && !in_array($name, $fields, true)) {
                continue;
            }

            $value = $property->getValue($entity);

            // Convert nested entities
            if ($value instanceof Entity) {
                $value = self::extract($value);
            }

            $data[self::toSnakeCase($name)] = $value;
        }

        return $data;
    }

    /**
     * Map database columns to entity properties.
     *
     * @param array<string, mixed> $row Database row with snake_case keys
     * @return array<string, mixed> Array with camelCase keys
     */
    public static function mapColumnsToProperties(array $row): array
    {
        $result = [];

        foreach ($row as $column => $value) {
            $result[self::toCamelCase($column)] = $value;
        }

        return $result;
    }

    /**
     * Map entity properties to database columns.
     *
     * @param array<string, mixed> $properties Array with camelCase keys
     * @return array<string, mixed> Array with snake_case keys
     */
    public static function mapPropertiesToColumns(array $properties): array
    {
        $result = [];

        foreach ($properties as $property => $value) {
            $result[self::toSnakeCase($property)] = $value;
        }

        return $result;
    }

    /**
     * Get the table name for an entity class.
     *
     * Converts class name to snake_case plural form.
     *
     * @param class-string<Entity> $entityClass
     */
    public static function getTableName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        $className = end($parts);

        return self::toSnakeCase($className) . 's';
    }

    /**
     * Get property names for an entity class.
     *
     * @param class-string<Entity> $entityClass
     * @return array<int, string>
     */
    public static function getPropertyNames(string $entityClass): array
    {
        $reflection = new ReflectionClass($entityClass);
        $properties = $reflection->getProperties();

        return array_map(
            fn(ReflectionProperty $prop) => $prop->getName(),
            $properties
        );
    }

    /**
     * Get column names for an entity class.
     *
     * @param class-string<Entity> $entityClass
     * @return array<int, string>
     */
    public static function getColumnNames(string $entityClass): array
    {
        return array_map(
            fn(string $prop) => self::toSnakeCase($prop),
            self::getPropertyNames($entityClass)
        );
    }

    /**
     * Convert snake_case to camelCase.
     */
    public static function toCamelCase(string $value): string
    {
        $value = ucwords(str_replace(['_', '-'], ' ', $value));
        $value = str_replace(' ', '', $value);

        return lcfirst($value);
    }

    /**
     * Convert camelCase to snake_case.
     */
    public static function toSnakeCase(string $value): string
    {
        $pattern = '/([a-z])([A-Z])/';
        $replacement = '$1_$2';

        return strtolower(preg_replace($pattern, $replacement, $value));
    }

    /**
     * Create a prepared statement placeholder string.
     *
     * @param array<string, mixed> $data
     * @return string e.g., ":id, :name, :email"
     */
    public static function createPlaceholders(array $data): string
    {
        $placeholders = array_map(
            fn(string $key) => ':' . $key,
            array_keys($data)
        );

        return implode(', ', $placeholders);
    }

    /**
     * Create an INSERT statement.
     *
     * @param array<string, mixed> $data
     */
    public static function createInsertSql(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = self::createPlaceholders($data);

        return "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    }

    /**
     * Create an UPDATE statement.
     *
     * @param array<string, mixed> $data
     */
    public static function createUpdateSql(string $table, array $data, string $primaryKey = 'id'): string
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            if ($column !== $primaryKey) {
                $sets[] = "{$column} = :{$column}";
            }
        }

        $setClause = implode(', ', $sets);

        return "UPDATE {$table} SET {$setClause} WHERE {$primaryKey} = :{$primaryKey}";
    }
}
