# Logging

Luminor provides a robust, PSR-3 compatible logging system with support for multiple channels and drivers.

## Configuration

The logging configuration is stored in `config/logging.php`:

```php
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'stderr'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'file',
            'path' => storage_path('logs/app.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'stderr' => [
            'driver' => 'stdout',
            'level' => 'error',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
```

## Basic Usage

### Using the Helper Function

```php
// Log a debug message
logger('User logged in', ['user_id' => 123]);

// Log an info message
info('Order processed', ['order_id' => 456]);

// Get the logger instance
$logger = logger();
$logger->error('Something went wrong');
```

### Using the LogManager

```php
use Luminor\DDD\Logging\LogManager;

$log = app(LogManager::class);

// Log to default channel
$log->info('Hello world');

// Log to a specific channel
$log->channel('daily')->warning('Low disk space');

// Log with context
$log->error('Failed to process payment', [
    'order_id' => 123,
    'error' => $exception->getMessage(),
]);
```

## Log Levels

Luminor supports all standard PSR-3 log levels:

| Level       | Description                       |
| ----------- | --------------------------------- |
| `emergency` | System is unusable                |
| `alert`     | Action must be taken immediately  |
| `critical`  | Critical conditions               |
| `error`     | Error conditions                  |
| `warning`   | Warning conditions                |
| `notice`    | Normal but significant conditions |
| `info`      | Informational messages            |
| `debug`     | Debug-level messages              |

## Available Drivers

### File Driver

Writes logs to files with automatic daily rotation:

```php
'daily' => [
    'driver' => 'file',
    'path' => storage_path('logs/app.log'),
    'level' => 'debug',
    'days' => 14,           // Keep logs for 14 days
    'permission' => 0644,   // File permissions
],
```

### Stdout Driver

Writes logs to stdout/stderr (ideal for containers and cloud environments):

```php
'stderr' => [
    'driver' => 'stdout',
    'level' => 'error',
],
```

### Stack Driver

Sends logs to multiple channels simultaneously:

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['daily', 'stderr'],
    'ignore_exceptions' => false,
],
```

### Null Driver

Discards all log messages (useful for testing):

```php
'null' => [
    'driver' => 'null',
],
```

### Array Driver

Stores logs in memory (useful for testing):

```php
'testing' => [
    'driver' => 'array',
],
```

## Context and Message Interpolation

Log messages support placeholder interpolation using curly braces:

```php
$log->info('User {username} logged in from {ip}', [
    'username' => 'john',
    'ip' => '192.168.1.1',
]);
// Output: User john logged in from 192.168.1.1
```

## Creating Custom Drivers

Implement the `LoggerInterface` to create custom log drivers:

```php
use Luminor\DDD\Logging\AbstractLogger;
use Luminor\DDD\Logging\LogLevel;

class SlackLogger extends AbstractLogger
{
    public function __construct(
        private string $webhookUrl,
        private string $channel = 'default',
    ) {}

    protected function write(LogLevel $level, string $message, array $context): void
    {
        // Send to Slack webhook
        $payload = [
            'text' => "[{$level->value}] {$message}",
            'channel' => $this->channel,
        ];

        // HTTP request to Slack...
    }
}
```

## Testing with Logs

Use the `ArrayLogger` driver for testing:

```php
use Luminor\DDD\Logging\Drivers\ArrayLogger;

$logger = new ArrayLogger();
$logger->info('Test message');

$logs = $logger->getRecords();
$this->assertCount(1, $logs);
$this->assertEquals('info', $logs[0]['level']);
```

## Service Provider

The `LoggingServiceProvider` is automatically registered and provides:

- `LogManager::class` - The log manager singleton
- `LoggerInterface::class` - Alias to the default channel

```php
// In your service provider
public function register(ContainerInterface $container): void
{
    $container->singleton(LogManager::class, function () {
        return new LogManager(config('logging'));
    });
}
```
