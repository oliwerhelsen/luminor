---
title: Session Management
layout: default
parent: Features
nav_order: 10
description: "Session handling with multiple storage drivers"
---

# Session Management

Luminor provides a flexible session management system with multiple storage drivers. Sessions allow you to persist user data across HTTP requests, essential for maintaining state in web applications.

## Table of Contents

- [Introduction](#introduction)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Available Drivers](#available-drivers)
- [Flash Data](#flash-data)
- [Session Lifecycle](#session-lifecycle)
- [Best Practices](#best-practices)

## Introduction

The session system provides:

- **Multiple Drivers** - Array, file, and database storage
- **Flash Messages** - Temporary data for next request
- **Secure Tokens** - CSRF protection integration
- **Type-safe API** - Strongly typed with PHP 8.2+

## Configuration

Register the session service provider:

```php
use Luminor\Session\SessionServiceProvider;

$kernel->registerServiceProvider(new SessionServiceProvider());
```

Configure session settings in `config/session.php`:

```php
return [
    'driver' => env('SESSION_DRIVER', 'file'),

    'lifetime' => 120, // Minutes

    'expire_on_close' => false,

    'encrypt' => false,

    'files' => __DIR__ . '/../storage/sessions',

    'connection' => env('SESSION_CONNECTION', null),

    'table' => 'sessions',

    'lottery' => [2, 100], // Garbage collection probability

    'cookie' => env('SESSION_COOKIE', 'luminor_session'),

    'path' => '/',

    'domain' => env('SESSION_DOMAIN', null),

    'secure' => env('SESSION_SECURE_COOKIE', false),

    'http_only' => true,

    'same_site' => 'lax',
];
```

## Basic Usage

### Storing Data

Store data in the session:

```php
use Luminor\Session\SessionInterface;

$session = $container->get(SessionInterface::class);

// Store a value
$session->put('user_id', 42);

// Store multiple values
$session->put([
    'user_id' => 42,
    'username' => 'john_doe',
    'role' => 'admin',
]);

// Push to array
$session->push('permissions', 'edit_posts');
$session->push('permissions', 'delete_users');
```

### Retrieving Data

Retrieve data from the session:

```php
// Get a value
$userId = $session->get('user_id');

// Get with default value
$theme = $session->get('theme', 'light');

// Get all session data
$all = $session->all();

// Check if exists
if ($session->has('user_id')) {
    // Key exists and is not null
}

// Check if exists and has value
if ($session->exists('user_id')) {
    // Key exists (even if null)
}
```

### Removing Data

Remove data from the session:

```php
// Remove a single item
$session->forget('user_id');

// Remove multiple items
$session->forget(['user_id', 'username']);

// Retrieve and remove
$userId = $session->pull('user_id');

// Clear all data
$session->flush();
```

## Available Drivers

### Array Driver

Stores session data in memory for the current request. Perfect for testing:

```php
'driver' => 'array',
```

**Use cases:**

- Testing
- Stateless APIs
- Development

**Example:**

```php
use Luminor\Session\Drivers\ArraySessionDriver;

$driver = new ArraySessionDriver();
$session = new Session($driver, 'test_session_id');

$session->put('test', 'value');
```

### File Driver

Stores session data in the filesystem:

```php
'driver' => 'file',
'files' => __DIR__ . '/../storage/sessions',
'lifetime' => 120, // minutes
```

**Features:**

- Simple and reliable
- No external dependencies
- Automatic garbage collection
- File locking for concurrency

**Use cases:**

- Small to medium applications
- Shared hosting environments
- Development and staging

**Storage location:**

```
storage/sessions/
├── sess_abc123def456...
├── sess_xyz789ghi012...
└── ...
```

### Database Driver

Stores session data in a database table:

```php
'driver' => 'database',
'connection' => env('SESSION_CONNECTION', null),
'table' => 'sessions',
```

**Features:**

- Centralized storage
- Easier to scale
- Query session data
- Better for load-balanced environments

**Use cases:**

- Production applications
- Multiple web servers
- Session analytics

**Migration:**

Create the sessions table:

```bash
php bin/luminor make:migration CreateSessionsTable
```

Or use the built-in stub:

```php
use Luminor\Database\Schema\Schema;

$schema->create('sessions', function($table) {
    $table->string('id')->primary();
    $table->unsignedBigInteger('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

## Flash Data

Flash data is session data that is only available for the next request, perfect for status messages.

### Storing Flash Data

```php
// Flash a single value
$session->flash('message', 'Profile updated successfully!');

// Flash multiple values
$session->flash([
    'message' => 'Profile updated!',
    'type' => 'success',
]);
```

### Using Flash Data

```php
// In the next request
if ($session->has('message')) {
    $message = $session->get('message');
    // Display: "Profile updated successfully!"
}
```

### Reflashing

Keep flash data for another request:

```php
// Keep all flash data
$session->reflash();

// Keep specific flash data
$session->keep(['message', 'type']);
```

### Common Pattern

Controller action:

```php
class UserController extends ApiController
{
    public function update(UpdateUserCommand $command): Response
    {
        $this->commandBus->execute($command);

        $this->session->flash('message', 'Profile updated successfully!');
        $this->session->flash('type', 'success');

        return $this->redirect('/profile');
    }
}
```

Template:

```php
<?php if ($session->has('message')): ?>
    <div class="alert alert-<?= $session->get('type', 'info') ?>">
        <?= $session->get('message') ?>
    </div>
<?php endif; ?>
```

## Session Lifecycle

### Session Start

Sessions start automatically on first access:

```php
// Starts session automatically
$session->put('user_id', 42);
```

Or start manually:

```php
$session->start();
```

### Session ID

Get or regenerate the session ID:

```php
// Get current session ID
$id = $session->getId();

// Regenerate ID (prevent session fixation)
$session->regenerate();

// Regenerate and delete old session
$session->regenerate(true);
```

### Session Regeneration

Always regenerate session ID after login:

```php
class AuthenticationService
{
    public function login(string $email, string $password): bool
    {
        if ($this->attemptLogin($email, $password)) {
            // Regenerate session ID to prevent fixation attacks
            $this->session->regenerate(true);

            $this->session->put('user_id', $user->id);
            $this->session->put('authenticated', true);

            return true;
        }

        return false;
    }

    public function logout(): void
    {
        $this->session->flush();
        $this->session->regenerate(true);
    }
}
```

### Session Invalidation

Invalidate a session:

```php
// Clear all data and regenerate
$session->invalidate();

// Same as:
$session->flush();
$session->regenerate(true);
```

### Session Migration

Migrate session to new ID:

```php
$session->migrate();
```

## Session Middleware

Use session middleware in your HTTP pipeline:

```php
use Luminor\Session\SessionMiddleware;

class Kernel
{
    protected array $middleware = [
        SessionMiddleware::class,
        // ... other middleware
    ];
}
```

The middleware handles:

- Starting the session
- Loading session data
- Saving session data
- Garbage collection

## Advanced Usage

### Age Flashing

Set data to expire after a certain time:

```php
$session->put('notification', 'New message', now()->addMinutes(5));
```

### Session Token

Generate a CSRF token:

```php
// Get or generate token
$token = $session->token();

// Regenerate token
$session->regenerateToken();
```

### Previous URL

Store and retrieve previous URL:

```php
// Store previous URL
$session->setPreviousUrl($request->url());

// Get previous URL
$previousUrl = $session->previousUrl();
```

### Increment/Decrement

Increment or decrement numeric values:

```php
$session->put('page_views', 0);

$session->increment('page_views');        // 1
$session->increment('page_views', 5);     // 6

$session->decrement('page_views');        // 5
$session->decrement('page_views', 2);     // 3
```

## Best Practices

### 1. Regenerate Session ID on Login

Prevent session fixation attacks:

```php
public function login(array $credentials): bool
{
    if ($this->validateCredentials($credentials)) {
        // IMPORTANT: Regenerate session ID
        $this->session->regenerate(true);

        $this->session->put('authenticated', true);
        $this->session->put('user_id', $user->id);

        return true;
    }

    return false;
}
```

### 2. Clear Session on Logout

```php
public function logout(): void
{
    // Clear all session data
    $this->session->flush();

    // Regenerate ID
    $this->session->regenerate(true);
}
```

### 3. Don't Store Sensitive Data

Avoid storing sensitive information in sessions:

```php
// Bad
$session->put('credit_card', '4111111111111111');
$session->put('password', 'secret123');

// Good
$session->put('user_id', 42);
$session->put('authenticated', true);
```

### 4. Use Flash for Temporary Messages

```php
// Good - using flash for one-time messages
$session->flash('success', 'User created successfully!');

// Bad - storing in regular session
$session->put('success', 'User created successfully!');
```

### 5. Validate Session Data

Always validate session data:

```php
$userId = $session->get('user_id');

if ($userId && $this->userRepository->exists($userId)) {
    $user = $this->userRepository->find($userId);
} else {
    // Invalid session, logout
    $session->flush();
}
```

### 6. Set Appropriate Lifetime

```php
// Short sessions for sensitive applications
'lifetime' => 15, // 15 minutes

// Longer sessions for general applications
'lifetime' => 120, // 2 hours

// Remember me functionality (separate mechanism)
'lifetime' => 43200, // 30 days
```

### 7. Use Database Driver for Load Balancing

For multiple web servers, use database driver:

```php
// config/session.php
'driver' => 'database',
'table' => 'sessions',
```

### 8. Enable HTTP Only Cookies

Prevent XSS attacks:

```php
'http_only' => true, // Prevent JavaScript access
'secure' => true,    // HTTPS only (in production)
'same_site' => 'lax', // CSRF protection
```

### 9. Implement Session Timeout

Check for session timeout:

```php
class SessionTimeoutMiddleware implements MiddlewareInterface
{
    private const TIMEOUT = 1800; // 30 minutes

    public function handle(Request $request, callable $next): Response
    {
        $lastActivity = $this->session->get('last_activity', 0);

        if (time() - $lastActivity > self::TIMEOUT) {
            $this->session->flush();
            return new RedirectResponse('/login?timeout=1');
        }

        $this->session->put('last_activity', time());

        return $next($request);
    }
}
```

### 10. Monitor Session Table Size

For database driver, regularly clean up old sessions:

```sql
-- Delete sessions older than 30 days
DELETE FROM sessions
WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY));
```

Or use automatic garbage collection:

```php
'lottery' => [2, 100], // 2% chance to run GC on each request
```

## Testing

Use the array driver for testing:

```php
use Luminor\Session\Drivers\ArraySessionDriver;
use Luminor\Session\Session;

class UserServiceTest extends TestCase
{
    protected SessionInterface $session;

    protected function setUp(): void
    {
        parent::setUp();
        $driver = new ArraySessionDriver();
        $this->session = new Session($driver, 'test_session_id');
    }

    public function test_stores_user_id_after_login(): void
    {
        $service = new AuthenticationService($this->session);
        $service->login('user@example.com', 'password');

        $this->assertTrue($this->session->has('user_id'));
        $this->assertTrue($this->session->get('authenticated'));
    }

    public function test_clears_session_on_logout(): void
    {
        $this->session->put('user_id', 42);
        $this->session->put('authenticated', true);

        $service = new AuthenticationService($this->session);
        $service->logout();

        $this->assertFalse($this->session->has('user_id'));
        $this->assertEmpty($this->session->all());
    }
}
```

## Security Considerations

### 1. Session Fixation

Always regenerate session ID on authentication state changes:

```php
// On login
$session->regenerate(true);

// On privilege escalation
$session->regenerate(true);
```

### 2. Session Hijacking

Mitigate with:

- HTTPS only (`secure` => true)
- HTTP only cookies (`http_only` => true)
- SameSite cookies (`same_site` => 'lax')
- IP address validation (optional)
- User agent validation (optional)

```php
class SessionValidationMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $storedIp = $this->session->get('ip_address');
        $currentIp = $request->getClientIp();

        if ($storedIp && $storedIp !== $currentIp) {
            // Potential session hijacking
            $this->session->flush();
            return new RedirectResponse('/login');
        }

        return $next($request);
    }
}
```

### 3. CSRF Protection

Use session tokens for CSRF protection:

```php
// Generate token
$token = $session->token();

// Verify token
if ($request->input('_token') !== $session->token()) {
    throw new CsrfException('Token mismatch');
}
```

See [Security Documentation](16-security.md#csrf-protection) for more details.

## Performance Considerations

### 1. Choose the Right Driver

- **Array**: Testing only
- **File**: Small to medium applications
- **Database**: Large applications, load balancing

### 2. Optimize Garbage Collection

```php
// Lower probability for high-traffic sites
'lottery' => [1, 1000], // 0.1% chance

// Higher probability for low-traffic sites
'lottery' => [5, 100],  // 5% chance
```

### 3. Limit Session Data

Store only essential data:

```php
// Good - minimal data
$session->put('user_id', 42);
$session->put('role', 'admin');

// Bad - too much data
$session->put('user', $user->toArray());
$session->put('permissions', Permission::all()->toArray());
```

### 4. Use Cache for Expensive Data

```php
// Bad - expensive data in session
$session->put('dashboard_stats', $this->calculateStats());

// Good - use cache
$stats = $cache->remember('dashboard_stats', 3600, function() {
    return $this->calculateStats();
});
```

## See Also

- [Security](16-security.md) - CSRF and authentication
- [HTTP Layer](05-http-layer.md) - Request and response handling
- [Cache](13-cache.md) - Caching strategies
- [Database](14-database.md) - Database driver configuration
