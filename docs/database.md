---
title: Database & Migrations
layout: default
parent: Features
nav_order: 9
description: "Schema builder, migrations, and multi-database support"
---

# Database & Migrations

Luminor provides a comprehensive database layer with schema building, migrations, and query execution capabilities. The system supports multiple database engines including MySQL, PostgreSQL, and SQLite.

## Table of Contents

- [Introduction](#introduction)
- [Configuration](#configuration)
- [Database Connection](#database-connection)
- [Schema Builder](#schema-builder)
- [Migrations](#migrations)
- [Migration Commands](#migration-commands)
- [Best Practices](#best-practices)

## Introduction

The database layer provides:

- **Schema Builder** - Fluent API for creating/modifying tables
- **Migrations** - Version control for your database schema
- **Multi-database Support** - MySQL, PostgreSQL, SQLite
- **Type-safe Operations** - Strong typing with PHP 8.2+

## Configuration

Configure your database connection in `config/database.php`:

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'luminor'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'luminor'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'schema' => 'public',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', __DIR__ . '/../storage/database.sqlite'),
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'path' => __DIR__ . '/../database/migrations',
    ],
];
```

Environment variables in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=luminor
DB_USERNAME=root
DB_PASSWORD=secret
```

## Database Connection

### Basic Usage

```php
use Luminor\Database\Connection;

// Get connection instance
$connection = $container->get(Connection::class);

// Execute raw query
$users = $connection->select('SELECT * FROM users WHERE active = ?', [true]);

// Insert
$connection->insert(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['John Doe', 'john@example.com']
);

// Update
$affected = $connection->update(
    'UPDATE users SET active = ? WHERE id = ?',
    [true, 1]
);

// Delete
$deleted = $connection->delete('DELETE FROM users WHERE id = ?', [1]);
```

### Transactions

```php
use Luminor\Database\Connection;

$connection->transaction(function($connection) {
    $connection->insert('INSERT INTO users (name) VALUES (?)', ['John']);
    $connection->insert('INSERT INTO profiles (user_id) VALUES (?)', [1]);
});

// Manual transaction control
$connection->beginTransaction();
try {
    $connection->insert('INSERT INTO users (name) VALUES (?)', ['John']);
    $connection->insert('INSERT INTO profiles (user_id) VALUES (?)', [1]);
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
```

## Schema Builder

The schema builder provides a fluent API for creating and modifying database tables.

### Creating Tables

```php
use Luminor\Database\Schema\Schema;

$schema = $container->get(Schema::class);

$schema->create('users', function($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```

### Available Column Types

```php
// Numeric
$table->id();                          // Auto-incrementing UNSIGNED BIGINT (primary key)
$table->bigInteger('votes');           // BIGINT
$table->integer('votes');              // INTEGER
$table->smallInteger('votes');         // SMALLINT
$table->tinyInteger('votes');          // TINYINT
$table->decimal('amount', 8, 2);       // DECIMAL with precision & scale
$table->float('amount', 8, 2);         // FLOAT

// String
$table->string('name', 100);           // VARCHAR with optional length
$table->text('description');           // TEXT
$table->mediumText('description');     // MEDIUMTEXT
$table->longText('description');       // LONGTEXT
$table->char('code', 4);               // CHAR with length

// Date & Time
$table->date('created_date');          // DATE
$table->datetime('created_at');        // DATETIME
$table->timestamp('added_on');         // TIMESTAMP
$table->time('sunrise');               // TIME
$table->year('birth_year');            // YEAR

// Boolean
$table->boolean('confirmed');          // BOOLEAN

// JSON
$table->json('options');               // JSON

// Binary
$table->binary('data');                // BLOB

// Special
$table->uuid();                        // UUID
$table->enum('role', ['admin', 'user']); // ENUM
$table->rememberToken();               // VARCHAR(100) for "remember me" tokens
$table->timestamps();                  // Created_at and updated_at
```

### Column Modifiers

```php
$table->string('email')->nullable();              // Allow NULL values
$table->string('name')->default('Guest');         // Default value
$table->integer('votes')->unsigned();             // Unsigned
$table->string('email')->unique();                // Unique constraint
$table->integer('user_id')->index();              // Add index
$table->string('description')->comment('User description'); // Add comment
```

### Foreign Keys

```php
$schema->create('posts', function($table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('title');
    $table->text('content');
    $table->timestamps();

    // Foreign key constraint
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade')
          ->onUpdate('cascade');
});
```

### Indexes

```php
// Single column index
$table->index('email');

// Composite index
$table->index(['account_id', 'created_at']);

// Unique index
$table->unique('email');
$table->unique(['account_id', 'email']);

// Custom index name
$table->index('email', 'idx_user_email');
```

### Modifying Tables

```php
// Add columns
$schema->table('users', function($table) {
    $table->string('phone')->nullable();
    $table->timestamp('last_login_at')->nullable();
});

// Drop columns
$schema->table('users', function($table) {
    $table->dropColumn('phone');
    $table->dropColumn(['phone', 'last_login_at']); // Multiple columns
});

// Rename column
$schema->table('users', function($table) {
    $table->renameColumn('name', 'full_name');
});

// Modify column
$schema->table('users', function($table) {
    $table->string('name', 200)->change(); // Increase length
});
```

### Dropping Tables

```php
// Drop table
$schema->drop('users');

// Drop table if exists
$schema->dropIfExists('users');
```

### Checking Table Existence

```php
if ($schema->hasTable('users')) {
    // Table exists
}

if ($schema->hasColumn('users', 'email')) {
    // Column exists
}
```

## Migrations

Migrations are version control for your database, allowing you to modify the database schema over time.

### Creating Migrations

Use the CLI to create a new migration:

```bash
php bin/luminor make:migration CreateUsersTable
```

This creates a file in `database/migrations/`:

```
database/migrations/2025_12_13_100000_create_users_table.php
```

### Migration Structure

```php
use Luminor\Database\Migrations\Migration;
use Luminor\Database\Schema\Schema;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('users', function($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
};
```

### Migration Examples

#### Creating a Posts Table

```bash
php bin/luminor make:migration CreatePostsTable
```

```php
return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('posts', function($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('content');
            $table->string('slug')->unique();
            $table->enum('status', ['draft', 'published', 'archived'])
                  ->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('posts');
    }
};
```

#### Adding Columns to Existing Table

```bash
php bin/luminor make:migration AddPhoneToUsersTable
```

```php
return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->table('users', function($table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function($table) {
            $table->dropColumn(['phone', 'avatar']);
        });
    }
};
```

#### Creating Pivot Table

```bash
php bin/luminor make:migration CreatePostTagTable
```

```php
return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('post_tag', function($table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('post_id')
                  ->references('id')
                  ->on('posts')
                  ->onDelete('cascade');

            $table->foreign('tag_id')
                  ->references('id')
                  ->on('tags')
                  ->onDelete('cascade');

            $table->unique(['post_id', 'tag_id']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('post_tag');
    }
};
```

## Migration Commands

### migrate

Run all pending migrations:

```bash
php bin/luminor migrate
```

Options:

- `--force` - Force the operation to run in production
- `--pretend` - Show the SQL that would be executed

```bash
# Run migrations in production
php bin/luminor migrate --force

# Preview SQL without executing
php bin/luminor migrate --pretend
```

### migrate:rollback

Rollback the last batch of migrations:

```bash
php bin/luminor migrate:rollback
```

Options:

- `--step=N` - Rollback N batches
- `--force` - Force the operation to run in production

```bash
# Rollback last 3 batches
php bin/luminor migrate:rollback --step=3

# Force rollback in production
php bin/luminor migrate:rollback --force
```

### migrate:reset

Rollback all migrations:

```bash
php bin/luminor migrate:reset
```

Options:

- `--force` - Force the operation to run in production

```bash
php bin/luminor migrate:reset --force
```

### migrate:fresh

Drop all tables and re-run all migrations:

```bash
php bin/luminor migrate:fresh
```

**Warning:** This will destroy all data in the database!

Options:

- `--force` - Force the operation to run in production
- `--seed` - Run seeders after migration (future feature)

```bash
# Fresh migration in development
php bin/luminor migrate:fresh

# Fresh migration with seeders
php bin/luminor migrate:fresh --seed
```

### migrate:status

Show the status of each migration:

```bash
php bin/luminor migrate:status
```

Output:

```
+------+-------------------------------------------------------+-------+
| Ran? | Migration                                             | Batch |
+------+-------------------------------------------------------+-------+
| Yes  | 2025_12_13_100000_create_users_table                  | 1     |
| Yes  | 2025_12_13_110000_create_posts_table                  | 1     |
| Yes  | 2025_12_13_120000_add_phone_to_users_table            | 2     |
| No   | 2025_12_13_130000_create_comments_table               |       |
+------+-------------------------------------------------------+-------+
```

## Best Practices

### 1. Never Modify Committed Migrations

Once a migration has been committed to version control and run in production, never modify it. Always create a new migration:

```php
// Bad - Modifying existing migration
// 2025_12_13_100000_create_users_table.php
$table->string('name', 200); // Changed from 100 to 200

// Good - Create new migration
// 2025_12_14_100000_increase_users_name_length.php
return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->table('users', function($table) {
            $table->string('name', 200)->change();
        });
    }
};
```

### 2. Always Define Rollback Logic

Always implement the `down()` method:

```php
public function up(Schema $schema): void
{
    $schema->create('users', function($table) {
        // Create table
    });
}

public function down(Schema $schema): void
{
    // Always provide rollback logic
    $schema->drop('users');
}
```

### 3. Use Descriptive Migration Names

```bash
# Good
php bin/luminor make:migration CreateUsersTable
php bin/luminor make:migration AddEmailVerifiedAtToUsersTable
php bin/luminor make:migration AddIndexToPostsStatusColumn

# Bad
php bin/luminor make:migration UpdateUsers
php bin/luminor make:migration ChangeTable
php bin/luminor make:migration NewMigration
```

### 4. Order Migrations Logically

Create tables before creating foreign keys:

```bash
# Correct order
php bin/luminor make:migration CreateUsersTable
php bin/luminor make:migration CreatePostsTable  # References users
php bin/luminor make:migration CreateCommentsTable  # References posts
```

### 5. Use Transactions (When Possible)

Most database operations in migrations are automatically wrapped in transactions. However, some DDL statements (like MySQL's ALTER TABLE) cannot be rolled back.

### 6. Test Migrations

Always test both up and down migrations:

```bash
# Run migration
php bin/luminor migrate

# Test rollback
php bin/luminor migrate:rollback

# Re-run migration
php bin/luminor migrate
```

### 7. Keep Migrations Small

Break complex schema changes into multiple migrations:

```bash
# Instead of one large migration, use multiple smaller ones
php bin/luminor make:migration AddUserProfileFields
php bin/luminor make:migration AddUserPreferences
php bin/luminor make:migration AddUserNotificationSettings
```

### 8. Use Indexes Wisely

Add indexes for columns used in WHERE, JOIN, and ORDER BY:

```php
$schema->create('posts', function($table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('status');
    $table->timestamp('published_at')->nullable();

    // Index foreign keys
    $table->index('user_id');

    // Index columns used in WHERE clauses
    $table->index('status');
    $table->index('published_at');

    // Composite index for common queries
    $table->index(['user_id', 'status']);
});
```

### 9. Document Complex Migrations

Add comments for complex operations:

```php
return new class extends Migration
{
    /**
     * Migrate user authentication from simple password to multi-factor.
     *
     * - Adds phone and phone_verified_at columns
     * - Adds two_factor_secret and recovery_codes columns
     * - Preserves existing password authentication
     */
    public function up(Schema $schema): void
    {
        $schema->table('users', function($table) {
            $table->string('phone', 20)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('recovery_codes')->nullable();
        });
    }
};
```

### 10. Handle Production Carefully

Use `--force` flag consciously in production:

```bash
# Development - runs automatically
php bin/luminor migrate

# Production - requires confirmation or --force
php bin/luminor migrate --force
```

## Database Grammars

Luminor supports multiple database grammars, automatically selecting the appropriate one based on your driver:

### MySQL

```php
use Luminor\Database\Schema\MySqlGrammar;

// Automatically used when driver is 'mysql'
$schema->create('users', function($table) {
    $table->id();
    $table->json('metadata');  // Generates JSON column
    $table->timestamps();      // Uses TIMESTAMP type
});
```

### PostgreSQL

```php
use Luminor\Database\Schema\PostgresGrammar;

// Automatically used when driver is 'pgsql'
$schema->create('users', function($table) {
    $table->id();
    $table->json('metadata');  // Generates JSONB column
    $table->uuid();            // Generates UUID type
    $table->timestamps();      // Uses TIMESTAMP type
});
```

### SQLite

```php
use Luminor\Database\Schema\SqliteGrammar;

// Automatically used when driver is 'sqlite'
$schema->create('users', function($table) {
    $table->id();
    $table->json('metadata');  // Generates TEXT column
    $table->timestamps();      // Uses TEXT type for timestamps
});
```

## Troubleshooting

### Migration Already Exists

```
Error: Migration file already exists
```

Solution: Use a different, more specific name for your migration.

### Foreign Key Constraint Fails

```
Error: Cannot add foreign key constraint
```

Solutions:

1. Ensure referenced table exists
2. Ensure referenced column exists
3. Ensure data types match exactly
4. Ensure referenced column is indexed

### Table Already Exists

```
Error: Table 'users' already exists
```

Solutions:

1. Check if migration was already run: `php bin/luminor migrate:status`
2. Use `dropIfExists()` instead of `drop()` in down method
3. Run `migrate:rollback` before re-running

## See Also

- [Repository Pattern](03-domain-layer.md#repositories) - Data access patterns
- [Console Commands](12-console.md) - CLI tools
- [Testing](08-testing.md) - Testing database operations
- [Cache](13-cache.md) - Caching query results
