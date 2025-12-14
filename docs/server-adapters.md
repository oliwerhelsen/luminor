# Server Adapters

Luminor supports multiple HTTP server backends, allowing you to choose the best option for your development and production environments.

## Available Servers

| Server           | Type         | Performance | Requirements      |
| ---------------- | ------------ | ----------- | ----------------- |
| **PHP Built-in** | `fpm`        | Standard    | None (default)    |
| **FrankenPHP**   | `frankenphp` | High        | FrankenPHP binary |

## Quick Start

```bash
# Default (PHP built-in server)
luminor serve

# With FrankenPHP
luminor serve --server=frankenphp

# List available servers
luminor serve --list-servers
```

## PHP Built-in Server (Default)

The default server uses PHP's built-in development server. It requires no additional extensions and works out of the box.

```bash
luminor serve
luminor serve --server=fpm
luminor serve --host=0.0.0.0 --port=8080
```

**Best for:** Local development, quick prototyping

## FrankenPHP

[FrankenPHP](https://frankenphp.dev/) is a modern PHP application server built on top of Caddy. It supports both classic mode (FPM drop-in) and worker mode (persistent processes).

### Installation

```bash
# Quick install
curl https://frankenphp.dev/install.sh | sh

# Using Docker
docker pull dunglas/frankenphp
```

### Usage

```bash
# Classic mode (FPM-compatible)
luminor serve --server=frankenphp

# Worker mode (high performance)
luminor serve --server=frankenphp --worker-mode

# With custom workers
luminor serve --server=frankenphp --worker-mode --workers=8
```

### Options

| Option          | Description                   | Default |
| --------------- | ----------------------------- | ------- |
| `--workers`     | Number of worker processes    | 4       |
| `--worker-mode` | Enable persistent worker mode | false   |

**Best for:** Production deployments, containerized environments, Caddy users

### Performance Considerations

- Worker mode is ~10x faster than PHP-FPM
- Built-in HTTPS with automatic certificates
- Excellent for containerized deployments
- Supports Early Hints (HTTP 103)

## Choosing a Server

### Development

For local development, the default PHP built-in server is usually sufficient:

```bash
luminor serve
```

### Production

For production, we recommend FrankenPHP:

```bash
# Modern with automatic HTTPS
luminor serve --server=frankenphp --worker-mode --workers=8
```

### Performance Comparison

| Server              | Requests/sec | Latency | Memory |
| ------------------- | ------------ | ------- | ------ |
| PHP-FPM             | ~1,000       | ~10ms   | High   |
| FrankenPHP (worker) | ~10,000      | ~1ms    | Low    |

_Benchmarks are approximate and vary based on application complexity._

## Programmatic Usage

You can also use the server adapters programmatically:

```php
use Luminor\Server\ServerFactory;
use Luminor\Server\ServerType;

// Create a specific server
$server = ServerFactory::create(ServerType::FRANKENPHP);

// Or from a string
$server = ServerFactory::createFromString('frankenphp');

// Get the best available server
$server = ServerFactory::getBestAvailable(preferHighPerformance: true);

// Check availability
if ($server->isAvailable()) {
    $server->start('127.0.0.1', 8000, '/path/to/public', [
        'workers' => 8,
    ]);
}

// List all available servers
$servers = ServerFactory::getAvailableServers();
foreach ($servers as $server) {
    echo $server->getName() . "\n";
}
```

## Stateless Application Requirements

When using FrankenPHP worker mode, your application must be stateless. This means:

1. **No global state** - Avoid storing request-specific data in global variables
2. **No static properties** - Static properties persist between requests
3. **Clean service containers** - Reset singletons between requests if needed
4. **Database connections** - Use connection pooling

### Example: Stateless Service

```php
// Good: Stateless service
class UserService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function findUser(string $id): ?User
    {
        return $this->repository->find($id);
    }
}

// Bad: Stateful service (avoid!)
class UserService
{
    private static array $cache = []; // Persists between requests!

    public function findUser(string $id): ?User
    {
        // This cache will grow indefinitely
        return self::$cache[$id] ??= $this->repository->find($id);
    }
}
```

## Docker Examples

### FrankenPHP

```dockerfile
FROM dunglas/frankenphp

COPY . /app
WORKDIR /app

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
```

## Troubleshooting

### FrankenPHP not found

```
Error: FrankenPHP is not installed
```

Install FrankenPHP:

```bash
curl https://frankenphp.dev/install.sh | sh
```

### Port already in use

```
Error: Port 8000 is already in use
```

Use a different port:

```bash
luminor serve --port=8080
```

### Memory leaks in worker mode

If you experience memory growth, ensure your application is stateless. Check for:

- Static property accumulation
- Unclosed database connections
- Growing caches or buffers
