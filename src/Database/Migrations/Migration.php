<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Migrations;

use Luminor\DDD\Database\Schema\Schema;

/**
 * Abstract Migration Base Class
 *
 * Provides common functionality for migrations including
 * access to the Schema builder.
 */
abstract class Migration implements MigrationInterface
{
    protected Schema $schema;
    private string $name;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;

        // Extract migration name from class name
        $className = static::class;
        $parts = explode('\\', $className);
        $this->name = end($parts);
    }

    /**
     * @inheritDoc
     */
    abstract public function up(): void;

    /**
     * @inheritDoc
     */
    abstract public function down(): void;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }
}
