---
title: JWT Authentication Tutorial
layout: default
parent: Authentication
nav_order: 2
description: "Learn how to implement JWT (JSON Web Token) authentication for stateless API authentication"
---

# JWT Authentication Tutorial

This tutorial walks you through implementing JWT (JSON Web Token) authentication for your API from scratch. By the end, you'll have a fully functional stateless authentication system.

## What You'll Learn

- How JWT authentication works
- Setting up the JWT service
- Creating login and registration endpoints
- Protecting API routes
- Implementing token refresh
- Handling token expiration gracefully

## Prerequisites

- Luminor framework installed
- Basic understanding of PHP and REST APIs
- A user model/entity in your application

---

## Step 1: Understanding JWT Authentication

JWT tokens consist of three parts:
1. **Header** - Algorithm and token type
2. **Payload** - Claims (user data, expiration, etc.)
3. **Signature** - Verification hash

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.   <- Header
eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6.. <- Payload
SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c <- Signature
```

The token is signed with a secret key, allowing the server to verify its authenticity without storing session state.

---

## Step 2: Configure Environment Variables

Add these to your `.env` file:

```bash
# JWT Configuration
JWT_SECRET="your-very-secure-secret-minimum-32-characters-long"
JWT_TTL=3600           # Access token lifetime (1 hour)
JWT_REFRESH_TTL=604800 # Refresh token lifetime (7 days)
JWT_ISSUER="your-app-name"
```

**Important**: Generate a secure secret key:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

---

## Step 3: Create the Authentication Service

Create a service class to handle authentication logic:

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\AuthenticationException;
use App\Domain\User\User;
use App\Domain\User\UserRepository;

final class AuthService
{
    public function __construct(
        private JwtService $jwtService,
        private UserRepository $userRepository,
    ) {}

    /**
     * Authenticate user with email and password
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            throw new AuthenticationException('Invalid credentials');
        }

        return $this->generateTokens($user);
    }

    /**
     * Register a new user
     */
    public function register(string $name, string $email, string $password): array
    {
        // Check if email already exists
        if ($this->userRepository->findByEmail($email)) {
            throw new AuthenticationException('Email already registered');
        }

        // Create the user
        $user = User::create(
            name: $name,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
        );

        $this->userRepository->save($user);

        return $this->generateTokens($user);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(string $refreshToken): array
    {
        try {
            $token = $this->jwtService->parse($refreshToken);

            // Verify it's a refresh token
            if ($token->getClaim('type') !== 'refresh') {
                throw new AuthenticationException('Invalid token type');
            }

            $userId = $token->getSubject();
            $user = $this->userRepository->find($userId);

            if (!$user) {
                throw new AuthenticationException('User not found');
            }

            return $this->generateTokens($user);

        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid refresh token: ' . $e->getMessage());
        }
    }

    /**
     * Generate access and refresh tokens for a user
     */
    private function generateTokens(User $user): array
    {
        $accessToken = $this->jwtService->generate($user->getId(), [
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ]);

        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        return [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $refreshToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => $accessToken->getTimeToExpiry(),
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
        ];
    }
}
```

---

## Step 4: Create the Authentication Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\DDD\Infrastructure\Http\ApiController;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\CurrentUser;
use App\Auth\AuthService;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

final class AuthController extends ApiController
{
    public function __construct(
        private AuthService $authService,
    ) {}

    /**
     * POST /auth/register
     */
    public function register(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        // Validate input
        $errors = $this->validate($payload, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        try {
            $tokens = $this->authService->register(
                name: $payload['name'],
                email: $payload['email'],
                password: $payload['password']
            );

            return $this->created($response, [
                'message' => 'Registration successful',
                'data' => $tokens,
            ]);

        } catch (AuthenticationException $e) {
            return $this->error($response, $e->getMessage(), 422);
        }
    }

    /**
     * POST /auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        // Validate input
        $errors = $this->validate($payload, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        try {
            $tokens = $this->authService->login(
                email: $payload['email'],
                password: $payload['password']
            );

            return $this->success($response, [
                'message' => 'Login successful',
                'data' => $tokens,
            ]);

        } catch (AuthenticationException $e) {
            return $this->unauthorized($response, 'Invalid credentials');
        }
    }

    /**
     * POST /auth/refresh
     */
    public function refresh(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        if (empty($payload['refresh_token'])) {
            return $this->error($response, 'Refresh token is required', 400);
        }

        try {
            $tokens = $this->authService->refresh($payload['refresh_token']);

            return $this->success($response, [
                'message' => 'Token refreshed',
                'data' => $tokens,
            ]);

        } catch (AuthenticationException $e) {
            return $this->unauthorized($response, $e->getMessage());
        }
    }

    /**
     * GET /auth/me
     */
    public function me(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();

        return $this->success($response, [
            'data' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
        ]);
    }

    /**
     * POST /auth/logout
     */
    public function logout(Request $request, Response $response): Response
    {
        // With stateless JWT, we simply tell the client to discard tokens
        // For token blacklisting, you would add the token to a blacklist here

        return $this->success($response, [
            'message' => 'Logged out successfully',
        ]);
    }
}
```

---

## Step 5: Set Up Routes

Configure your routes in `routes/api.php`:

```php
<?php

use App\Http\Controllers\AuthController;
use Luminor\Auth\RateLimit\RateLimitMiddleware;
use Luminor\Infrastructure\Http\Middleware\AuthenticationMiddleware;

// Public auth routes (with rate limiting)
$router->group(['prefix' => '/auth'], function ($router) use ($rateLimiter) {

    $router->post('/register', [AuthController::class, 'register'])
        ->middleware(new RateLimitMiddleware($rateLimiter, maxAttempts: 10));

    $router->post('/login', [AuthController::class, 'login'])
        ->middleware(new RateLimitMiddleware($rateLimiter, maxAttempts: 5));

    $router->post('/refresh', [AuthController::class, 'refresh'])
        ->middleware(new RateLimitMiddleware($rateLimiter, maxAttempts: 10));
});

// Protected auth routes
$router->group(['prefix' => '/auth', 'middleware' => $authMiddleware], function ($router) {

    $router->get('/me', [AuthController::class, 'me']);
    $router->post('/logout', [AuthController::class, 'logout']);
});
```

---

## Step 6: Configure Authentication Provider

In your bootstrap or service provider:

```php
<?php

use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Jwt\JwtAuthenticationProvider;
use Luminor\Infrastructure\Http\Middleware\AuthenticationMiddleware;
use App\Domain\User\UserRepository;

// Initialize JWT Service
$jwtService = new JwtService(
    secret: $_ENV['JWT_SECRET'],
    ttl: (int) ($_ENV['JWT_TTL'] ?? 3600),
    issuer: $_ENV['JWT_ISSUER'] ?? 'luminor'
);

// Initialize Authentication Manager
$authManager = new AuthenticationManager();

// User resolver - fetches user from database by ID
$userRepository = $container->get(UserRepository::class);
$userResolver = fn($userId) => $userRepository->find($userId);

// Register JWT provider
$jwtProvider = new JwtAuthenticationProvider($jwtService, $userResolver);
$authManager->register($jwtProvider);

// Create authentication middleware
$authMiddleware = new AuthenticationMiddleware($authManager, required: true);

// Register in container
$container->set(JwtService::class, $jwtService);
$container->set(AuthenticationManager::class, $authManager);
```

---

## Step 7: Test Your API

### Register a new user

```bash
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "securepassword123",
    "password_confirmation": "securepassword123"
  }'
```

**Response:**
```json
{
  "message": "Registration successful",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "john@example.com"
    }
  }
}
```

### Login

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "securepassword123"
  }'
```

### Access protected route

```bash
curl http://localhost:8080/auth/me \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..."
```

### Refresh token

```bash
curl -X POST http://localhost:8080/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
  }'
```

---

## Step 8: Handle Token Expiration (Client-Side)

Here's how to handle token refresh in a JavaScript client:

```javascript
class AuthClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.accessToken = localStorage.getItem('access_token');
    this.refreshToken = localStorage.getItem('refresh_token');
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    };

    if (this.accessToken) {
      headers['Authorization'] = `Bearer ${this.accessToken}`;
    }

    let response = await fetch(url, { ...options, headers });

    // If token expired, try to refresh
    if (response.status === 401 && this.refreshToken) {
      const refreshed = await this.refreshAccessToken();

      if (refreshed) {
        // Retry the original request with new token
        headers['Authorization'] = `Bearer ${this.accessToken}`;
        response = await fetch(url, { ...options, headers });
      }
    }

    return response;
  }

  async refreshAccessToken() {
    try {
      const response = await fetch(`${this.baseUrl}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: this.refreshToken }),
      });

      if (response.ok) {
        const data = await response.json();
        this.setTokens(data.data.access_token, data.data.refresh_token);
        return true;
      }
    } catch (error) {
      console.error('Token refresh failed:', error);
    }

    this.clearTokens();
    return false;
  }

  setTokens(accessToken, refreshToken) {
    this.accessToken = accessToken;
    this.refreshToken = refreshToken;
    localStorage.setItem('access_token', accessToken);
    localStorage.setItem('refresh_token', refreshToken);
  }

  clearTokens() {
    this.accessToken = null;
    this.refreshToken = null;
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }
}

// Usage
const auth = new AuthClient('http://localhost:8080');

// Login
const loginResponse = await auth.request('/auth/login', {
  method: 'POST',
  body: JSON.stringify({ email: 'john@example.com', password: 'secret' }),
});
const { data } = await loginResponse.json();
auth.setTokens(data.access_token, data.refresh_token);

// Make authenticated requests
const meResponse = await auth.request('/auth/me');
const user = await meResponse.json();
```

---

## Advanced: Token Blacklisting

For enhanced security, implement token blacklisting for logout and compromised tokens:

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Luminor\Cache\Cache;

final class TokenBlacklist
{
    public function __construct(
        private Cache $cache,
    ) {}

    /**
     * Add a token to the blacklist
     */
    public function add(string $token, int $expiresAt): void
    {
        $ttl = $expiresAt - time();

        if ($ttl > 0) {
            $tokenHash = hash('sha256', $token);
            $this->cache->set("blacklist:{$tokenHash}", true, $ttl);
        }
    }

    /**
     * Check if a token is blacklisted
     */
    public function isBlacklisted(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        return $this->cache->has("blacklist:{$tokenHash}");
    }
}
```

Then modify your JWT provider to check the blacklist:

```php
// In a custom JwtAuthenticationProvider
public function authenticate(Request $request): ?AuthenticatableInterface
{
    $token = $this->extractToken($request);

    if (!$token) {
        return null;
    }

    // Check blacklist
    if ($this->blacklist->isBlacklisted($token)) {
        throw new AuthenticationException('Token has been revoked');
    }

    // Continue with normal validation...
}
```

---

## Best Practices Summary

1. **Use strong secrets** - At least 256 bits of entropy
2. **Short access token TTL** - 15 minutes to 1 hour
3. **Longer refresh token TTL** - 7 to 30 days
4. **Rotate refresh tokens** - Issue new refresh token with each refresh
5. **Rate limit auth endpoints** - Prevent brute force attacks
6. **Use HTTPS** - Never send tokens over unencrypted connections
7. **Store tokens securely** - HttpOnly cookies or secure storage
8. **Implement token blacklisting** - For logout and security incidents

---

## Next Steps

- [Add Multi-Factor Authentication](./mfa-authentication.md)
- [Implement Role-Based Access Control](./authorization-rbac.md)
- [Set Up OpenID Connect SSO](./openid-connect-sso.md)

---

## Troubleshooting

### "Token expired" errors
- Check your server's time synchronization
- Verify `JWT_TTL` is set correctly
- Ensure client clock is synchronized

### "Invalid signature" errors
- Verify `JWT_SECRET` matches across all servers
- Ensure the secret hasn't been changed
- Check for whitespace in environment variable

### Tokens not being extracted
- Verify `Authorization: Bearer <token>` header format
- Check for middleware order issues
- Ensure the auth provider is registered
