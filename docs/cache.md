---
title: Cache
layout: default
parent: Features
nav_order: 8
description: "Caching system with array and file drivers"
---

# Cache

Luminor provides a powerful and flexible caching system that supports multiple drivers. The cache system follows a clean interface-based design, making it easy to swap between different cache backends.

## Table of Contents

- [Introduction](#introduction)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Available Drivers](#available-drivers)
- [Cache Methods](#cache-methods)
- [Advanced Usage](#advanced-usage)

## Introduction

The cache system in Luminor provides a unified API for storing and retrieving data from various cache stores. This helps improve application performance by reducing database queries and expensive computations.

## Configuration

First, register the cache service provider in your application:

```php
use Luminor\Cache\CacheServiceProvider;

$kernel->registerServiceProvider(new CacheServiceProvider());
```

Configure your cache settings in `config/cache.php`:

```php
return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache',
        ],
    ],
];
```

## Basic Usage

### Storing Items

Store an item in the cache for a specified duration:

```php
use Luminor\Cache\CacheManager;

$cache = $container->get(CacheManager::class);

// Store for 60 seconds
$cache->put('key', 'value', 60);

// Store forever
$cache->forever('key', 'value');
```

### Retrieving Items

Retrieve items from the cache:

```php
// Get a value
$value = $cache->get('key');

// Get with default value if not found
$value = $cache->get('key', 'default');

// Get with closure as default
$value = $cache->get('key', function() {
    return 'computed value';
});
```

### Checking Existence

Check if an item exists in the cache:

```php
if ($cache->has('key')) {
    // Item exists
}
```

### Removing Items

Remove items from the cache:

```php
// Remove a single item
$cache->forget('key');

// Clear all items
$cache->flush();
```

## Available Drivers

### Array Driver

The array driver stores cache data in memory for the current request. Perfect for testing:

```php
'stores' => [
    'array' => [
        'driver' => 'array',
    ],
],
```

**Use cases:**

- Testing
- Development
- Temporary caching within a single request

### File Driver

The file driver stores cache data in the filesystem:

```php
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => __DIR__ . '/../storage/cache',
    ],
],
```

**Features:**

- Persistent storage
- Automatic serialization
- TTL support
- Garbage collection

**Use cases:**

- Production applications
- Persistent caching across requests
- Small to medium-sized applications

## Cache Methods

### put()

Store an item in the cache:

```php
// Store for 60 seconds
$cache->put('user.1', $user, 60);

// Store with Carbon
$cache->put('user.1', $user, now()->addHour());
```

### get()

Retrieve an item from the cache:

```php
$user = $cache->get('user.1');

// With default value
$user = $cache->get('user.1', function() {
    return User::find(1);
});
```

### add()

Store an item only if it doesn't exist:

```php
// Returns true if stored, false if key already exists
$added = $cache->add('key', 'value', 60);
```

### forever()

Store an item indefinitely:

```php
$cache->forever('settings', $settings);
```

### remember()

Get an item or store and return default:

```php
$users = $cache->remember('users', 3600, function() {
    return User::all();
});
```

### rememberForever()

Get an item or store and return default forever:

```php
$config = $cache->rememberForever('config', function() {
    return loadConfiguration();
});
```

### pull()

Retrieve and delete an item:

```php
$value = $cache->pull('key');
```

### increment() / decrement()

Increment or decrement a value:

```php
$cache->put('views', 0);
$cache->increment('views');        // 1
$cache->increment('views', 5);     // 6
$cache->decrement('views');        // 5
$cache->decrement('views', 2);     // 3
```

## Advanced Usage

### Multiple Stores

Use different cache stores in the same application:

```php
// Use default store
$cache->put('key', 'value', 60);

// Use specific store
$cache->store('array')->put('key', 'value', 60);
$cache->store('file')->put('key', 'value', 60);
```

### Cache Tags (Future Feature)

```php
// Will be available in future versions
$cache->tags(['people', 'artists'])->put('John', $john, 60);
$cache->tags(['people', 'authors'])->put('Anne', $anne, 60);

// Flush all people
$cache->tags(['people'])->flush();
```

### Cache Events (Future Feature)

```php
// Will be available in future versions
$cache->listen('hit', function($key, $value) {
    // Cache hit
});

$cache->listen('miss', function($key) {
    // Cache miss
});
```

### Custom Cache Driver

Create a custom cache driver by implementing `CacheInterface`:

```php
use Luminor\Cache\CacheInterface;

class RedisCache implements CacheInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    // Implement other methods...
}
```

Register your custom driver:

```php
$cacheManager->extend('redis', function($config) {
    $redis = new \Redis();
    $redis->connect($config['host'], $config['port']);
    return new RedisCache($redis);
});
```

## Best Practices

### 1. Use Meaningful Keys

Use descriptive and consistent key naming:

```php
// Good
$cache->put('user.1.profile', $profile, 3600);
$cache->put('posts.recent.10', $posts, 600);

// Bad
$cache->put('u1p', $profile, 3600);
$cache->put('pr10', $posts, 600);
```

### 2. Set Appropriate TTL

Choose TTL based on data change frequency:

```php
// Rarely changes - long TTL
$cache->put('site.settings', $settings, 86400); // 24 hours

// Changes frequently - short TTL
$cache->put('trending.posts', $posts, 300); // 5 minutes

// Static data - forever
$cache->forever('countries', $countries);
```

### 3. Use Cache for Expensive Operations

Cache database queries and API calls:

```php
$statistics = $cache->remember('dashboard.stats', 3600, function() {
    return [
        'users' => User::count(),
        'posts' => Post::count(),
        'revenue' => Order::sum('total'),
    ];
});
```

### 4. Invalidate When Data Changes

Clear cache when data is modified:

```php
class UserService
{
    public function updateUser(int $id, array $data): User
    {
        $user = User::find($id);
        $user->update($data);

        // Invalidate cache
        $this->cache->forget("user.{$id}");
        $this->cache->forget("user.{$id}.profile");

        return $user;
    }
}
```

### 5. Handle Cache Failures Gracefully

Always provide fallbacks:

```php
try {
    $data = $cache->get('key');
    if ($data === null) {
        $data = $this->fetchFromDatabase();
        $cache->put('key', $data, 3600);
    }
} catch (\Exception $e) {
    // Log error and continue without cache
    $data = $this->fetchFromDatabase();
}
```

## Testing

Use the array driver for testing:

```php
use Luminor\Cache\Drivers\ArrayCache;

class UserServiceTest extends TestCase
{
    protected CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCache();
    }

    public function test_caches_user_data(): void
    {
        $service = new UserService($this->cache);
        $user = $service->getUser(1);

        $this->assertTrue($this->cache->has('user.1'));
        $this->assertEquals($user, $this->cache->get('user.1'));
    }
}
```

## Performance Considerations

### 1. Cache Size

Monitor cache size, especially with file driver:

```bash
# Check cache directory size
du -sh storage/cache
```

### 2. Serialization Overhead

Large objects have serialization overhead:

```php
// Good - cache processed data
$cache->put('user.stats', [
    'post_count' => 42,
    'follower_count' => 1337,
], 3600);

// Bad - cache entire user object with relations
$cache->put('user.full', $user->load('posts', 'followers'), 3600);
```

### 3. Cache Warming

Pre-populate cache for critical data:

```php
class CacheWarmer
{
    public function warm(): void
    {
        // Warm frequently accessed data
        $this->cache->put('popular.posts',
            Post::popular()->limit(10)->get(),
            3600
        );

        $this->cache->put('active.users',
            User::active()->get(),
            1800
        );
    }
}
```

## Future Enhancements

The following features are planned for future releases:

- Redis driver
- Memcached driver
- Cache tags
- Cache events
- Atomic locks
- Cache clearing by pattern
- Cache statistics and monitoring

## See Also

- [Queues](10-queues.md) - Background job processing
- [Database](14-database.md) - Database operations
- [Configuration](02-quick-start.md#configuration) - Application configuration
