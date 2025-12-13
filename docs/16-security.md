# Security

Lumina provides robust security features to protect your application from common vulnerabilities. This includes password hashing, CSRF protection, and secure authentication mechanisms.

## Table of Contents

- [Introduction](#introduction)
- [Password Hashing](#password-hashing)
- [CSRF Protection](#csrf-protection)
- [Security Best Practices](#security-best-practices)
- [Common Vulnerabilities](#common-vulnerabilities)

## Introduction

Security features in Lumina:

- **Password Hashing** - Bcrypt and Argon2id support
- **CSRF Protection** - Token-based request verification
- **Secure Sessions** - HTTP-only, secure cookies
- **Input Validation** - Request validation system

## Password Hashing

Lumina provides a secure password hashing system with support for multiple algorithms.

### Configuration

Register the security service provider:

```php
use Lumina\Security\SecurityServiceProvider;

$kernel->registerServiceProvider(new SecurityServiceProvider());
```

Configure in `config/hashing.php`:

```php
return [
    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],

    'argon2id' => [
        'memory' => 65536,    // 64 MB
        'time' => 4,          // 4 iterations
        'threads' => 1,       // Single thread
    ],
];
```

### Basic Usage

Hash and verify passwords:

```php
use Lumina\Security\HashManager;

$hashManager = $container->get(HashManager::class);

// Hash a password
$hashed = $hashManager->make('secret-password');

// Verify a password
if ($hashManager->check('secret-password', $hashed)) {
    // Password is correct
}

// Check if rehash is needed
if ($hashManager->needsRehash($hashed)) {
    $newHash = $hashManager->make('secret-password');
    // Update in database
}
```

### Available Hashers

#### Bcrypt

Industry standard, widely supported:

```php
use Lumina\Security\BcryptHasher;

$hasher = new BcryptHasher(12); // 12 rounds

$hash = $hasher->hash('password');
$valid = $hasher->verify('password', $hash);
```

**Advantages:**
- Battle-tested
- Widely supported
- Good security

**Configuration:**
```php
'driver' => 'bcrypt',
'bcrypt' => [
    'rounds' => 12, // 10-12 recommended
],
```

#### Argon2id

Modern algorithm, recommended for new applications:

```php
use Lumina\Security\Argon2IdHasher;

$hasher = new Argon2IdHasher(
    memory: 65536,    // 64 MB
    time: 4,          // 4 iterations
    threads: 1        // 1 thread
);

$hash = $hasher->hash('password');
$valid = $hasher->verify('password', $hash);
```

**Advantages:**
- More secure than bcrypt
- Resistant to GPU attacks
- Configurable memory usage

**Configuration:**
```php
'driver' => 'argon2id',
'argon2id' => [
    'memory' => 65536,  // Memory in KB
    'time' => 4,        // Time cost
    'threads' => 1,     // Parallelism
],
```

### User Authentication Example

```php
use Lumina\Security\HashManager;

class UserService
{
    public function __construct(
        private HashManager $hash,
        private UserRepository $users
    ) {}

    public function register(string $email, string $password): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hash->make($password));

        $this->users->save($user);

        return $user;
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!$this->hash->check($password, $user->getPassword())) {
            return null;
        }

        // Check if password needs rehashing (algorithm upgraded)
        if ($this->hash->needsRehash($user->getPassword())) {
            $user->setPassword($this->hash->make($password));
            $this->users->save($user);
        }

        return $user;
    }
}
```

### Password Rehashing

Automatically upgrade password hashes when algorithm changes:

```php
class LoginController extends ApiController
{
    public function login(Request $request): Response
    {
        $user = $this->authenticateUser(
            $request->input('email'),
            $request->input('password')
        );

        if (!$user) {
            return $this->error('Invalid credentials', 401);
        }

        // Check if rehash needed (e.g., switched from bcrypt to argon2id)
        if ($this->hash->needsRehash($user->getPassword())) {
            $user->setPassword(
                $this->hash->make($request->input('password'))
            );
            $this->users->save($user);
        }

        return $this->success(['user' => $user]);
    }
}
```

## CSRF Protection

Cross-Site Request Forgery (CSRF) protection prevents unauthorized commands from being transmitted from a user that the web application trusts.

### How CSRF Works

1. User logs in to your application
2. Attacker tricks user into submitting a form to your application
3. Without CSRF protection, the request would be accepted
4. With CSRF protection, the request is rejected due to missing/invalid token

### Configuration

The CSRF middleware uses session tokens to verify requests.

### Using CSRF Middleware

```php
use Lumina\Infrastructure\Http\Middleware\CsrfMiddleware;

class Kernel
{
    protected array $middleware = [
        SessionMiddleware::class,
        CsrfMiddleware::class, // Must be after SessionMiddleware
    ];
}
```

### CSRF Token in Forms

Include CSRF token in all non-GET requests:

```php
<form method="POST" action="/users">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">

    <input type="email" name="email">
    <input type="password" name="password">

    <button type="submit">Create User</button>
</form>
```

### CSRF Token in AJAX

Include token in AJAX requests:

```javascript
// Get token from meta tag
const token = document.querySelector('meta[name="csrf-token"]').content;

// Include in fetch request
fetch('/api/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify({ email: 'user@example.com' })
});
```

HTML meta tag:

```html
<head>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
```

### Excluding Routes

Exclude certain routes from CSRF protection:

```php
use Lumina\Infrastructure\Http\Middleware\CsrfMiddleware;

class CustomCsrfMiddleware extends CsrfMiddleware
{
    protected array $except = [
        '/api/webhooks/*',
        '/api/public/*',
    ];
}
```

### CSRF Token API

```php
use Lumina\Security\Csrf\CsrfToken;

// Generate new token
$token = CsrfToken::generate();

// Get current token from session
$token = $session->token();

// Regenerate token
$session->regenerateToken();

// Verify token
if ($token->verify($request->input('_token'))) {
    // Token is valid
}
```

### Custom CSRF Validation

```php
use Lumina\Security\Csrf\CsrfException;

class OrderController extends ApiController
{
    public function create(Request $request): Response
    {
        // Manual CSRF validation
        if ($request->input('_token') !== $this->session->token()) {
            throw new CsrfException('CSRF token mismatch');
        }

        // Process order...
    }
}
```

## Security Best Practices

### 1. Use HTTPS in Production

Always use HTTPS in production:

```php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),

// Redirect HTTP to HTTPS
if (!$request->isSecure() && $this->app->isProduction()) {
    return new RedirectResponse(
        'https://' . $request->getHost() . $request->getRequestUri(),
        301
    );
}
```

### 2. Set Secure Cookie Flags

```php
'http_only' => true,     // Prevent JavaScript access
'secure' => true,        // HTTPS only
'same_site' => 'strict', // CSRF protection
```

### 3. Implement Rate Limiting

Prevent brute force attacks:

```php
class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_MINUTES = 15;

    public function tooManyAttempts(string $key): bool
    {
        $attempts = $this->cache->get("login_attempts:{$key}", 0);
        return $attempts >= self::MAX_ATTEMPTS;
    }

    public function hit(string $key): void
    {
        $attempts = $this->cache->get("login_attempts:{$key}", 0);
        $this->cache->put(
            "login_attempts:{$key}",
            $attempts + 1,
            self::DECAY_MINUTES * 60
        );
    }

    public function clear(string $key): void
    {
        $this->cache->forget("login_attempts:{$key}");
    }
}
```

Usage:

```php
class LoginController extends ApiController
{
    public function login(Request $request): Response
    {
        $key = $request->input('email');

        if ($this->limiter->tooManyAttempts($key)) {
            return $this->error('Too many login attempts', 429);
        }

        $user = $this->authenticate(
            $request->input('email'),
            $request->input('password')
        );

        if (!$user) {
            $this->limiter->hit($key);
            return $this->error('Invalid credentials', 401);
        }

        $this->limiter->clear($key);
        return $this->success(['user' => $user]);
    }
}
```

### 4. Validate All Input

Always validate user input:

```php
use Lumina\Validation\Validator;

class CreateUserCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {}

    public function validate(): array
    {
        $validator = new Validator([
            'email' => $this->email,
            'password' => $this->password,
        ], [
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'password'],
        ]);

        return $validator->errors();
    }
}
```

### 5. Sanitize Output

Prevent XSS attacks:

```php
// Escape HTML
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// Use template engine with auto-escaping
{{ $userInput }} // Auto-escaped
{!! $trustedHtml !!} // Raw output (use sparingly)
```

### 6. Use Prepared Statements

Prevent SQL injection:

```php
// Good - prepared statement
$connection->select(
    'SELECT * FROM users WHERE email = ?',
    [$email]
);

// Bad - string concatenation
$connection->select(
    "SELECT * FROM users WHERE email = '{$email}'"
);
```

### 7. Implement Content Security Policy

```php
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
```

### 8. Store Secrets Securely

Never commit secrets to version control:

```php
// .env file (not in version control)
APP_KEY=base64:random-generated-key
DB_PASSWORD=secret-password
API_KEY=secret-api-key

// .env.example (in version control)
APP_KEY=
DB_PASSWORD=
API_KEY=
```

### 9. Implement Proper Authorization

Check permissions before actions:

```php
use Lumina\Auth\AuthorizationService;

class PostController extends ApiController
{
    public function delete(int $id): Response
    {
        $post = $this->posts->find($id);

        if (!$this->authz->can($this->currentUser, 'delete', $post)) {
            return $this->error('Unauthorized', 403);
        }

        $this->posts->delete($post);
        return $this->success();
    }
}
```

### 10. Log Security Events

Log authentication and authorization failures:

```php
class SecurityLogger
{
    public function logFailedLogin(string $email, string $ip): void
    {
        $this->logger->warning('Failed login attempt', [
            'email' => $email,
            'ip' => $ip,
            'timestamp' => time(),
        ]);
    }

    public function logUnauthorizedAccess(string $resource, string $action): void
    {
        $this->logger->warning('Unauthorized access attempt', [
            'user_id' => $this->currentUser->getId(),
            'resource' => $resource,
            'action' => $action,
            'ip' => $this->request->getClientIp(),
        ]);
    }
}
```

## Common Vulnerabilities

### SQL Injection

**Vulnerable:**
```php
$sql = "SELECT * FROM users WHERE email = '{$_POST['email']}'";
$result = $connection->query($sql);
```

**Secure:**
```php
$result = $connection->select(
    'SELECT * FROM users WHERE email = ?',
    [$_POST['email']]
);
```

### Cross-Site Scripting (XSS)

**Vulnerable:**
```php
echo "<div>" . $_POST['comment'] . "</div>";
```

**Secure:**
```php
echo "<div>" . htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8') . "</div>";
```

### Cross-Site Request Forgery (CSRF)

**Vulnerable:**
```html
<form method="POST" action="/delete-account">
    <button>Delete Account</button>
</form>
```

**Secure:**
```html
<form method="POST" action="/delete-account">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <button>Delete Account</button>
</form>
```

### Session Fixation

**Vulnerable:**
```php
public function login(string $email, string $password): void
{
    if ($this->authenticate($email, $password)) {
        $this->session->put('user_id', $user->id);
    }
}
```

**Secure:**
```php
public function login(string $email, string $password): void
{
    if ($this->authenticate($email, $password)) {
        $this->session->regenerate(true); // Regenerate session ID
        $this->session->put('user_id', $user->id);
    }
}
```

### Insecure Direct Object Reference (IDOR)

**Vulnerable:**
```php
public function show(int $id): Response
{
    $order = $this->orders->find($id);
    return $this->success(['order' => $order]);
}
```

**Secure:**
```php
public function show(int $id): Response
{
    $order = $this->orders->find($id);

    if ($order->getUserId() !== $this->currentUser->getId()) {
        return $this->error('Unauthorized', 403);
    }

    return $this->success(['order' => $order]);
}
```

### Mass Assignment

**Vulnerable:**
```php
public function update(Request $request, int $id): Response
{
    $user = $this->users->find($id);
    $user->fill($request->all()); // Allows any field to be updated
    $this->users->save($user);
}
```

**Secure:**
```php
public function update(Request $request, int $id): Response
{
    $user = $this->users->find($id);
    $user->fill($request->only(['name', 'email'])); // Only allowed fields
    $this->users->save($user);
}
```

## Security Checklist

Use this checklist for security reviews:

- [ ] All passwords are hashed with bcrypt or argon2id
- [ ] CSRF protection is enabled for all state-changing requests
- [ ] Session IDs are regenerated on login/logout
- [ ] All user input is validated
- [ ] All output is sanitized/escaped
- [ ] SQL queries use prepared statements
- [ ] HTTPS is enforced in production
- [ ] Secure cookie flags are set (httponly, secure, samesite)
- [ ] Rate limiting is implemented for authentication
- [ ] Authorization checks are in place
- [ ] Security headers are set (CSP, X-Frame-Options, etc.)
- [ ] Secrets are not in version control
- [ ] Error messages don't leak sensitive information
- [ ] File uploads are validated and sanitized
- [ ] Dependencies are regularly updated

## See Also

- [Session Management](15-session.md) - Session handling
- [Validation](17-validation.md) - Input validation
- [Authentication & Authorization](../src/Auth/README.md) - Auth system
- [HTTP Layer](05-http-layer.md) - Request handling
