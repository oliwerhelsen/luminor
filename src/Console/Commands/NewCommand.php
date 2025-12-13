<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

/**
 * Command to create a new Luminor project.
 *
 * Scaffolds a complete DDD project structure with configurable options
 * for project type, database, multi-tenancy, and more.
 */
final class NewCommand extends AbstractCommand
{
    private const PROJECT_TYPES = [
        'basic' => 'Basic API (flat DDD structure)',
        'modular' => 'Modular Application (module-based architecture)',
    ];

    private const DATABASE_OPTIONS = [
        'none' => 'None (no database configuration)',
        'mysql' => 'MySQL',
        'postgres' => 'PostgreSQL',
        'sqlite' => 'SQLite',
    ];

    private const MULTITENANCY_OPTIONS = [
        'none' => 'Disabled (single tenant)',
        'header' => 'Header-based (X-Tenant-ID header)',
        'subdomain' => 'Subdomain-based (tenant.example.com)',
        'path' => 'Path-based (/tenants/{id}/...)',
    ];

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('new')
            ->setDescription('Create a new Luminor project')
            ->addArgument('name', [
                'description' => 'The name of the project (directory name)',
                'required' => true,
            ])
            ->addOption('vendor', [
                'description' => 'The vendor name for composer (e.g., acme)',
            ])
            ->addOption('namespace', [
                'description' => 'The root namespace (default: App)',
                'default' => 'App',
            ])
            ->addOption('type', [
                'shortcut' => 't',
                'description' => 'Project type: basic or modular',
            ])
            ->addOption('database', [
                'shortcut' => 'd',
                'description' => 'Database type: none, mysql, postgres, or sqlite',
            ])
            ->addOption('multitenancy', [
                'shortcut' => 'm',
                'description' => 'Multi-tenancy strategy: none, header, subdomain, or path',
            ])
            ->addOption('git', [
                'description' => 'Initialize a git repository (default: true)',
                'default' => true,
            ])
            ->addOption('no-git', [
                'description' => 'Skip git repository initialization',
            ])
            ->addOption('no-install', [
                'description' => 'Skip running composer install',
            ])
            ->addOption('no-interaction', [
                'shortcut' => 'n',
                'description' => 'Do not ask any interactive questions',
            ])
            ->addOption('force', [
                'shortcut' => 'f',
                'description' => 'Overwrite existing directory',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');
        if ($name === null || $name === '') {
            $output->error('Project name is required.');
            return 1;
        }

        $projectPath = getcwd() . DIRECTORY_SEPARATOR . $name;

        // Check if directory exists
        if (is_dir($projectPath) && !$input->hasOption('force')) {
            $output->error(sprintf('Directory "%s" already exists.', $name));
            $output->line('Use --force to overwrite.');
            return 1;
        }

        $interactive = !$input->hasOption('no-interaction');

        // Display welcome message
        $output->newLine();
        $output->writeln('<info>╔═══════════════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║</info>         <comment>Welcome to the Luminor Project Creator</comment>          <info>║</info>');
        $output->writeln('<info>╚═══════════════════════════════════════════════════════════╝</info>');
        $output->newLine();

        // Gather configuration
        $config = $this->gatherConfiguration($input, $output, $name, $interactive);

        if ($config === null) {
            $output->error('Configuration cancelled.');
            return 1;
        }

        $output->newLine();
        $output->info('Creating project: ' . $name);
        $output->newLine();

        // Create project directory
        if (!$this->createDirectory($projectPath, $output)) {
            return 1;
        }

        // Create directory structure
        $this->createDirectoryStructure($projectPath, $config, $output);

        // Create files from configuration
        $this->createProjectFiles($projectPath, $config, $output);

        // Create .gitkeep files
        $this->createGitKeepFiles($projectPath, $config);

        // Initialize git repository
        if ($config['git'] && !$input->hasOption('no-git')) {
            $this->initializeGit($projectPath, $output);
        }

        // Run composer install
        if (!$input->hasOption('no-install')) {
            $this->runComposerInstall($projectPath, $output);
        }

        // Display success message
        $this->displaySuccessMessage($output, $name, $config);

        return 0;
    }

    /**
     * Gather configuration from options or interactive prompts.
     *
     * @return array<string, mixed>|null
     */
    private function gatherConfiguration(
        Input $input,
        Output $output,
        string $name,
        bool $interactive
    ): ?array {
        $config = [
            'name' => $name,
            'vendor' => $input->getOption('vendor'),
            'namespace' => $input->getOption('namespace') ?? 'App',
            'type' => $input->getOption('type'),
            'database' => $input->getOption('database'),
            'multitenancy' => $input->getOption('multitenancy'),
            'git' => !$input->hasOption('no-git'),
        ];

        if ($interactive) {
            // Vendor name
            if ($config['vendor'] === null) {
                $suggestedVendor = $this->toKebabCase($name);
                $config['vendor'] = $output->ask('Vendor name (for composer)', $suggestedVendor);
            }

            // Namespace
            if ($input->getOption('namespace') === null || $input->getOption('namespace') === 'App') {
                $suggestedNamespace = $this->toPascalCase($name);
                $config['namespace'] = $output->ask('Root namespace', $suggestedNamespace);
            }

            // Project type
            if ($config['type'] === null) {
                $output->newLine();
                $config['type'] = $output->choice(
                    'What type of project do you want to create?',
                    self::PROJECT_TYPES,
                    'basic'
                );
            }

            // Database
            if ($config['database'] === null) {
                $output->newLine();
                $config['database'] = $output->choice(
                    'Which database would you like to use?',
                    self::DATABASE_OPTIONS,
                    'none'
                );
            }

            // Multi-tenancy
            if ($config['multitenancy'] === null) {
                $output->newLine();
                $config['multitenancy'] = $output->choice(
                    'Do you need multi-tenancy support?',
                    self::MULTITENANCY_OPTIONS,
                    'none'
                );
            }

            // Git initialization
            if (!$input->hasOption('no-git') && $input->getOption('git') === null) {
                $output->newLine();
                $config['git'] = $output->confirm('Initialize a git repository?', true);
            }
        } else {
            // Set defaults for non-interactive mode
            $config['vendor'] = $config['vendor'] ?? $this->toKebabCase($name);
            $config['namespace'] = $config['namespace'] ?? 'App';
            $config['type'] = $config['type'] ?? 'basic';
            $config['database'] = $config['database'] ?? 'none';
            $config['multitenancy'] = $config['multitenancy'] ?? 'none';
        }

        // Validate options
        if (!isset(self::PROJECT_TYPES[$config['type']])) {
            $output->error(sprintf('Invalid project type: %s', $config['type']));
            return null;
        }

        if (!isset(self::DATABASE_OPTIONS[$config['database']])) {
            $output->error(sprintf('Invalid database type: %s', $config['database']));
            return null;
        }

        if (!isset(self::MULTITENANCY_OPTIONS[$config['multitenancy']])) {
            $output->error(sprintf('Invalid multitenancy option: %s', $config['multitenancy']));
            return null;
        }

        return $config;
    }

    /**
     * Create a directory with error handling.
     */
    private function createDirectory(string $path, Output $output): bool
    {
        if (is_dir($path)) {
            // Remove existing directory if force is used
            $this->removeDirectory($path);
        }

        if (!mkdir($path, 0755, true)) {
            $output->error(sprintf('Failed to create directory: %s', $path));
            return false;
        }

        return true;
    }

    /**
     * Remove a directory recursively.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }

    /**
     * Create the directory structure based on project type.
     *
     * @param array<string, mixed> $config
     */
    private function createDirectoryStructure(string $projectPath, array $config, Output $output): void
    {
        $directories = [
            'config',
            'public',
            'tests/Unit',
            'tests/Integration',
            'stubs',
        ];

        if ($config['type'] === 'modular') {
            $directories = array_merge($directories, [
                'src/Modules',
            ]);
        } else {
            // Basic DDD structure
            $directories = array_merge($directories, [
                'src/Domain/Entities',
                'src/Domain/ValueObjects',
                'src/Domain/Events',
                'src/Domain/Repository',
                'src/Domain/Exceptions',
                'src/Application/Commands',
                'src/Application/Queries',
                'src/Application/Handlers',
                'src/Application/DTOs',
                'src/Application/Services',
                'src/Infrastructure/Http/Controllers',
                'src/Infrastructure/Http/Middleware',
                'src/Infrastructure/Persistence',
                'src/Infrastructure/Providers',
            ]);
        }

        // Add migrations directory if database is selected
        if ($config['database'] !== 'none') {
            $directories[] = 'database/migrations';
        }

        foreach ($directories as $dir) {
            $fullPath = $projectPath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                $output->line(sprintf('  <comment>Created:</comment> %s/', $dir));
            }
        }
    }

    /**
     * Create project files from templates.
     *
     * @param array<string, mixed> $config
     */
    private function createProjectFiles(string $projectPath, array $config, Output $output): void
    {
        // Composer.json
        $this->createComposerJson($projectPath, $config, $output);

        // .env.example
        $this->createEnvExample($projectPath, $config, $output);

        // .gitignore
        $this->createGitignore($projectPath, $output);

        // README.md
        $this->createReadme($projectPath, $config, $output);

        // Config files
        $this->createFrameworkConfig($projectPath, $config, $output);
        $this->createLoggingConfig($projectPath, $output);

        if ($config['database'] !== 'none') {
            $this->createDatabaseConfig($projectPath, $config, $output);
        }

        // Public index.php
        $this->createIndexPhp($projectPath, $config, $output);

        // PHPUnit config
        $this->createPhpunitConfig($projectPath, $config, $output);
    }

    /**
     * Create composer.json file.
     *
     * @param array<string, mixed> $config
     */
    private function createComposerJson(string $projectPath, array $config, Output $output): void
    {
        $vendor = $config['vendor'];
        $name = $this->toKebabCase($config['name']);
        $namespace = $config['namespace'];

        $require = [
            'php' => '^8.2',
            'luminor/ddd-framework' => '^1.0',
            'vlucas/phpdotenv' => '^5.5',
        ];

        $requireDev = [
            'phpunit/phpunit' => '^10.5',
        ];

        // Add database dependencies
        if ($config['database'] !== 'none') {
            $require['doctrine/dbal'] = '^4.0';
            $require['doctrine/migrations'] = '^3.0';
        }

        $composer = [
            'name' => "$vendor/$name",
            'description' => 'A Luminor DDD application',
            'type' => 'project',
            'license' => 'proprietary',
            'require' => $require,
            'require-dev' => $requireDev,
            'autoload' => [
                'psr-4' => [
                    "$namespace\\" => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    "Tests\\" => 'tests/',
                ],
            ],
            'scripts' => [
                'test' => 'phpunit',
                'serve' => 'php -S localhost:8000 -t public',
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        $content = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($projectPath . '/composer.json', $content);
        $output->line('  <comment>Created:</comment> composer.json');
    }

    /**
     * Create .env.example file.
     *
     * @param array<string, mixed> $config
     */
    private function createEnvExample(string $projectPath, array $config, Output $output): void
    {
        $appName = $this->toPascalCase($config['name']);

        $content = <<<ENV
# Application
APP_NAME={$appName}
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

ENV;

        if ($config['database'] !== 'none') {
            $dbDriver = $config['database'];
            $dbHost = $dbDriver === 'sqlite' ? '' : '127.0.0.1';
            $dbPort = match ($dbDriver) {
                'mysql' => '3306',
                'postgres' => '5432',
                default => '',
            };
            $dbDatabase = $dbDriver === 'sqlite' ? 'database/database.sqlite' : $this->toSnakeCase($config['name']);

            $content .= <<<ENV
# Database
DB_CONNECTION={$dbDriver}
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbDatabase}
DB_USERNAME=root
DB_PASSWORD=

ENV;
        }

        if ($config['multitenancy'] !== 'none') {
            $strategy = $config['multitenancy'];
            $headerName = $strategy === 'header' ? 'X-Tenant-ID' : '';

            $content .= <<<ENV
# Multi-tenancy
TENANT_ENABLED=true
TENANT_STRATEGY={$strategy}
TENANT_HEADER_NAME={$headerName}
TENANT_DEFAULT=

ENV;
        }

        $content .= <<<ENV
# Logging
LOG_CHANNEL=file
LOG_LEVEL=debug

ENV;

        file_put_contents($projectPath . '/.env.example', $content);
        // Also create .env from example
        file_put_contents($projectPath . '/.env', $content);
        $output->line('  <comment>Created:</comment> .env.example');
        $output->line('  <comment>Created:</comment> .env');
    }

    /**
     * Create .gitignore file.
     */
    private function createGitignore(string $projectPath, Output $output): void
    {
        $content = <<<'GITIGNORE'
# Dependencies
/vendor/

# Environment
.env
.env.local
.env.*.local

# IDE
/.idea/
/.vscode/
*.swp
*.swo
.DS_Store

# Logs
*.log
/storage/logs/

# Cache
/storage/cache/
/bootstrap/cache/

# Testing
.phpunit.result.cache
/coverage/

# Database
*.sqlite
*.sqlite3

# Build artifacts
/build/

GITIGNORE;

        file_put_contents($projectPath . '/.gitignore', $content);
        $output->line('  <comment>Created:</comment> .gitignore');
    }

    /**
     * Create README.md file.
     *
     * @param array<string, mixed> $config
     */
    private function createReadme(string $projectPath, array $config, Output $output): void
    {
        $name = $this->toPascalCase($config['name']);
        $type = self::PROJECT_TYPES[$config['type']];
        $database = self::DATABASE_OPTIONS[$config['database']];
        $multitenancy = self::MULTITENANCY_OPTIONS[$config['multitenancy']];

        $content = <<<README
# {$name}

A Luminor DDD Framework application.

## Project Configuration

- **Type:** {$type}
- **Database:** {$database}
- **Multi-tenancy:** {$multitenancy}

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer

### Installation

1. Install dependencies:
   ```bash
   composer install
   ```

2. Configure your environment:
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

3. Start the development server:
   ```bash
   composer serve
   # or
   php -S localhost:8000 -t public
   ```

## Directory Structure

```
{$config['name']}/
├── config/           # Configuration files
├── public/           # Web entry point
├── src/              # Application source code
│   ├── Domain/       # Domain layer (entities, value objects, events)
│   ├── Application/  # Application layer (commands, queries, handlers)
│   └── Infrastructure/  # Infrastructure layer (persistence, HTTP)
├── tests/            # Test files
└── stubs/            # Custom code generation stubs
```

## Available Commands

```bash
# Start development server
composer serve

# Run tests
composer test

# Generate code
vendor/bin/luminor make:entity MyEntity
vendor/bin/luminor make:command CreateMyEntity
vendor/bin/luminor make:query GetMyEntity
vendor/bin/luminor make:controller MyEntityController
```

## Documentation

For full documentation, visit the [Luminor DDD Framework documentation](https://github.com/luminor/ddd-framework).

## License

Proprietary

README;

        file_put_contents($projectPath . '/README.md', $content);
        $output->line('  <comment>Created:</comment> README.md');
    }

    /**
     * Create framework config file.
     *
     * @param array<string, mixed> $config
     */
    private function createFrameworkConfig(string $projectPath, array $config, Output $output): void
    {
        $appName = $this->toPascalCase($config['name']);
        $namespace = $config['namespace'];

        $multitenancyConfig = '';
        if ($config['multitenancy'] !== 'none') {
            $strategy = $config['multitenancy'];
            $multitenancyConfig = <<<PHP

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'multitenancy' => [
        'enabled' => env('TENANT_ENABLED', true),
        'strategy' => env('TENANT_STRATEGY', '{$strategy}'),
        'header_name' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),
        'default_tenant' => env('TENANT_DEFAULT'),
    ],
PHP;
        }

        $modulesConfig = '';
        if ($config['type'] === 'modular') {
            $modulesConfig = <<<PHP

    /*
    |--------------------------------------------------------------------------
    | Modules Configuration
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'path' => base_path('src/Modules'),
        'namespace' => '{$namespace}\\Modules',
        'enabled' => [
            // List your enabled modules here
            // {$namespace}\\Modules\\Example\\ExampleModule::class,
        ],
    ],
PHP;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', '{$appName}'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    */
    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        // {$namespace}\\Infrastructure\\Providers\\AppServiceProvider::class,
    ],
{$multitenancyConfig}{$modulesConfig}
];

PHP;

        file_put_contents($projectPath . '/config/framework.php', $content);
        $output->line('  <comment>Created:</comment> config/framework.php');
    }

    /**
     * Create logging config file.
     */
    private function createLoggingConfig(string $projectPath, Output $output): void
    {
        $content = <<<'PHP'
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */
    'default' => env('LOG_CHANNEL', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => base_path('storage/logs/app.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stdout' => [
            'driver' => 'stdout',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => ['file', 'stdout'],
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];

PHP;

        file_put_contents($projectPath . '/config/logging.php', $content);
        $output->line('  <comment>Created:</comment> config/logging.php');
    }

    /**
     * Create database config file.
     *
     * @param array<string, mixed> $config
     */
    private function createDatabaseConfig(string $projectPath, array $config, Output $output): void
    {
        $defaultDb = $config['database'];

        $content = <<<PHP
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => env('DB_CONNECTION', '{$defaultDb}'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', base_path('database/database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'luminor'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],

        'postgres' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'luminor'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Settings
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table' => 'migrations',
        'path' => base_path('database/migrations'),
    ],
];

PHP;

        file_put_contents($projectPath . '/config/database.php', $content);
        $output->line('  <comment>Created:</comment> config/database.php');
    }

    /**
     * Create public/index.php file.
     *
     * @param array<string, mixed> $config
     */
    private function createIndexPhp(string $projectPath, array $config, Output $output): void
    {
        $namespace = $config['namespace'];
        $isModular = $config['type'] === 'modular';

        $moduleLoader = '';
        if ($isModular) {
            $moduleLoader = <<<'PHP'

// Load modules
$modules = $config['modules']['enabled'] ?? [];
foreach ($modules as $moduleClass) {
    if (class_exists($moduleClass)) {
        $module = new $moduleClass();
        $module->register($kernel);
    }
}

PHP;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

use Luminor\DDD\Kernel;

/*
|--------------------------------------------------------------------------
| Bootstrap Application
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    \$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    \$dotenv->load();
}

// Load configuration
\$config = require __DIR__ . '/../config/framework.php';

// Create the kernel
\$kernel = new Kernel([
    'base_path' => dirname(__DIR__),
    'config_path' => dirname(__DIR__) . '/config',
]);
{$moduleLoader}
/*
|--------------------------------------------------------------------------
| Handle Request
|--------------------------------------------------------------------------
*/

\$router = \$kernel->getRouter();

// Define your routes here
\$router->get('/', function () {
    return [
        'name' => env('APP_NAME', 'Luminor'),
        'status' => 'running',
        'timestamp' => date('c'),
    ];
});

\$router->get('/health', function () {
    return ['status' => 'ok'];
});

// Example resource routes (uncomment and modify as needed)
// \$router->get('/api/v1/resources', [{$namespace}\\Infrastructure\\Http\\Controllers\\ResourceController::class, 'index']);
// \$router->get('/api/v1/resources/{id}', [{$namespace}\\Infrastructure\\Http\\Controllers\\ResourceController::class, 'show']);
// \$router->post('/api/v1/resources', [{$namespace}\\Infrastructure\\Http\\Controllers\\ResourceController::class, 'store']);
// \$router->put('/api/v1/resources/{id}', [{$namespace}\\Infrastructure\\Http\\Controllers\\ResourceController::class, 'update']);
// \$router->delete('/api/v1/resources/{id}', [{$namespace}\\Infrastructure\\Http\\Controllers\\ResourceController::class, 'destroy']);

// Run the application
\$kernel->run();

PHP;

        file_put_contents($projectPath . '/public/index.php', $content);
        $output->line('  <comment>Created:</comment> public/index.php');
    }

    /**
     * Create phpunit.xml configuration file.
     *
     * @param array<string, mixed> $config
     */
    private function createPhpunitConfig(string $projectPath, array $config, Output $output): void
    {
        $namespace = $config['namespace'];

        $content = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>

XML;

        file_put_contents($projectPath . '/phpunit.xml', $content);
        $output->line('  <comment>Created:</comment> phpunit.xml');
    }

    /**
     * Create .gitkeep files in empty directories.
     *
     * @param array<string, mixed> $config
     */
    private function createGitKeepFiles(string $projectPath, array $config): void
    {
        $directories = [];

        if ($config['type'] === 'modular') {
            $directories = [
                'src/Modules',
                'tests/Unit',
                'tests/Integration',
                'stubs',
            ];
        } else {
            $directories = [
                'src/Domain/Entities',
                'src/Domain/ValueObjects',
                'src/Domain/Events',
                'src/Domain/Repository',
                'src/Domain/Exceptions',
                'src/Application/Commands',
                'src/Application/Queries',
                'src/Application/Handlers',
                'src/Application/DTOs',
                'src/Application/Services',
                'src/Infrastructure/Http/Controllers',
                'src/Infrastructure/Http/Middleware',
                'src/Infrastructure/Persistence',
                'src/Infrastructure/Providers',
                'tests/Unit',
                'tests/Integration',
                'stubs',
            ];
        }

        if ($config['database'] !== 'none') {
            $directories[] = 'database/migrations';
        }

        foreach ($directories as $dir) {
            $gitkeep = $projectPath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . '.gitkeep';
            if (!file_exists($gitkeep)) {
                file_put_contents($gitkeep, '');
            }
        }
    }

    /**
     * Initialize a git repository.
     */
    private function initializeGit(string $projectPath, Output $output): void
    {
        $output->newLine();
        $output->info('Initializing git repository...');

        $cwd = getcwd();
        chdir($projectPath);

        // Initialize git
        exec('git init -q 2>&1', $initOutput, $initResult);

        if ($initResult === 0) {
            // Add all files
            exec('git add . 2>&1', $addOutput, $addResult);

            if ($addResult === 0) {
                // Create initial commit
                exec('git commit -q -m "Initial commit" 2>&1', $commitOutput, $commitResult);

                if ($commitResult === 0) {
                    $output->success('Git repository initialized with initial commit.');
                } else {
                    $output->warning('Git initialized but initial commit failed.');
                }
            } else {
                $output->warning('Git initialized but adding files failed.');
            }
        } else {
            $output->warning('Failed to initialize git repository.');
        }

        chdir($cwd);
    }

    /**
     * Run composer install in the project directory.
     */
    private function runComposerInstall(string $projectPath, Output $output): void
    {
        $output->newLine();
        $output->info('Installing dependencies...');
        $output->line('  Running: composer install');
        $output->newLine();

        $cwd = getcwd();
        chdir($projectPath);

        // Run composer install
        passthru('composer install 2>&1', $result);

        if ($result === 0) {
            $output->newLine();
            $output->success('Dependencies installed successfully.');
        } else {
            $output->newLine();
            $output->warning('Composer install finished with warnings. Please check the output above.');
        }

        chdir($cwd);
    }

    /**
     * Display success message with next steps.
     *
     * @param array<string, mixed> $config
     */
    private function displaySuccessMessage(Output $output, string $name, array $config): void
    {
        $output->newLine();
        $output->writeln('<info>╔═══════════════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║</info>              <success>✓ Project created successfully!</success>             <info>║</info>');
        $output->writeln('<info>╚═══════════════════════════════════════════════════════════╝</info>');
        $output->newLine();

        $output->comment('Next steps:');
        $output->newLine();
        $output->line("  1. <info>cd {$name}</info>");
        $output->line('  2. <info>cp .env.example .env</info> (configure your environment)');
        $output->line('  3. <info>composer serve</info> (start development server)');
        $output->newLine();

        $output->comment('Useful commands:');
        $output->newLine();
        $output->line('  <info>vendor/bin/luminor make:entity</info>      Create a new entity');
        $output->line('  <info>vendor/bin/luminor make:command</info>     Create a new command');
        $output->line('  <info>vendor/bin/luminor make:query</info>       Create a new query');
        $output->line('  <info>vendor/bin/luminor make:controller</info>  Create a new controller');

        if ($config['type'] === 'modular') {
            $output->line('  <info>vendor/bin/luminor make:module</info>     Create a new module');
        }

        $output->newLine();
        $output->info('Happy coding with Luminor! 🚀');
        $output->newLine();
    }

    /**
     * Convert string to kebab-case.
     */
    private function toKebabCase(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', '-', $value) ?? $value;
        $value = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        return strtolower(trim($value, '-'));
    }

    /**
     * Convert string to PascalCase.
     */
    private function toPascalCase(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', ' ', $value) ?? $value;
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /**
     * Convert string to snake_case.
     */
    private function toSnakeCase(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', '_', $value) ?? $value;
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        return strtolower(trim($value, '_'));
    }
}
