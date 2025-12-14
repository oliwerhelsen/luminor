<?php

declare(strict_types=1);

namespace Luminor\Database\Migrations;

/**
 * Migration Interface
 *
 * Defines the contract for database migrations.
 * Each migration must implement up() and down() methods.
 */
interface MigrationInterface
{
    /**
     * Run the migration.
     *
     * This method is called when running migrations forward.
     */
    public function up(): void;

    /**
     * Reverse the migration.
     *
     * This method is called when rolling back migrations.
     */
    public function down(): void;

    /**
     * Get the migration name.
     *
     * @return string The unique name of this migration
     */
    public function getName(): string;
}
