---
title: API Tokens Tutorial
layout: default
parent: Authentication
nav_order: 1
description: "Learn how to implement API token authentication for service accounts and integrations"
---

# API Tokens Tutorial

This tutorial walks you through implementing API token authentication for service accounts, CLI tools, and third-party integrations.

## What You'll Learn

- When to use API tokens vs JWT
- Creating and managing API tokens
- Implementing scope-based permissions
- Token security best practices
- Building a token management UI

## Prerequisites

- Luminor framework installed
- A database for storing tokens
- User authentication already set up

---

## API Tokens vs JWT

| Feature | API Tokens | JWT |
|---------|-----------|-----|
| **Lifetime** | Long-lived (months/years) | Short-lived (minutes/hours) |
| **Storage** | Database | Stateless (no storage) |
| **Revocation** | Immediate | Requires blacklist |
| **Use Case** | Service accounts, integrations | User sessions, mobile apps |
| **Scopes** | Fine-grained | Typically user-level |

**Use API tokens when:**
- Building integrations with third-party services
- Creating service accounts for automation
- Providing CLI tool authentication
- Allowing users to create personal access tokens

---

## Step 1: Database Setup

Create the API tokens table:

```php
<?php

use Luminor\Database\Migration;
use Luminor\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->schema->create('api_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('name');
            $table->string('token_hash', 64)->unique(); // SHA-256 hash
            $table->json('scopes')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema->drop('api_tokens');
    }
};
```

---

## Step 2: Define Available Scopes

Create a scopes configuration:

```php
<?php

declare(strict_types=1);

namespace App\Auth;

final class TokenScopes
{
    // User management
    public const USERS_READ = 'users:read';
    public const USERS_WRITE = 'users:write';
    public const USERS_DELETE = 'users:delete';

    // Posts/Content
    public const POSTS_READ = 'posts:read';
    public const POSTS_WRITE = 'posts:write';
    public const POSTS_DELETE = 'posts:delete';

    // Settings
    public const SETTINGS_READ = 'settings:read';
    public const SETTINGS_WRITE = 'settings:write';

    // Admin (all permissions)
    public const ADMIN = 'admin';

    /**
     * Get all available scopes with descriptions
     */
    public static function all(): array
    {
        return [
            self::USERS_READ => 'Read user information',
            self::USERS_WRITE => 'Create and update users',
            self::USERS_DELETE => 'Delete users',
            self::POSTS_READ => 'Read posts and content',
            self::POSTS_WRITE => 'Create and update posts',
            self::POSTS_DELETE => 'Delete posts',
            self::SETTINGS_READ => 'Read application settings',
            self::SETTINGS_WRITE => 'Modify application settings',
            self::ADMIN => 'Full administrative access',
        ];
    }

    /**
     * Validate scopes array
     */
    public static function validate(array $scopes): bool
    {
        $valid = array_keys(self::all());

        foreach ($scopes as $scope) {
            if (!in_array($scope, $valid, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a scope grants access to another scope
     */
    public static function grants(array $userScopes, string $requiredScope): bool
    {
        // Admin scope grants everything
        if (in_array(self::ADMIN, $userScopes, true)) {
            return true;
        }

        // Check direct scope
        if (in_array($requiredScope, $userScopes, true)) {
            return true;
        }

        // Check wildcard scopes (e.g., users:* grants users:read)
        $category = explode(':', $requiredScope)[0] ?? '';
        $wildcard = $category . ':*';

        return in_array($wildcard, $userScopes, true);
    }
}
```

---

## Step 3: API Token Entity

```php
<?php

declare(strict_types=1);

namespace App\Domain\ApiToken;

use Luminor\DDD\Domain\Abstractions\Entity;

final class ApiToken extends Entity
{
    private string $userId;
    private string $name;
    private string $tokenHash;
    private array $scopes;
    private ?string $lastUsedIp;
    private ?\DateTimeImmutable $lastUsedAt;
    private ?\DateTimeImmutable $expiresAt;
    private \DateTimeImmutable $createdAt;

    // Plain token (only available at creation time)
    private ?string $plainToken = null;

    public static function create(
        string $userId,
        string $name,
        array $scopes = [],
        ?int $expiresInSeconds = null,
    ): self {
        $token = new self(self::generateId());
        $token->userId = $userId;
        $token->name = $name;
        $token->scopes = $scopes;
        $token->createdAt = new \DateTimeImmutable();

        // Generate secure random token
        $plainToken = bin2hex(random_bytes(40)); // 80 character hex string
        $token->plainToken = $plainToken;
        $token->tokenHash = hash('sha256', $plainToken);

        if ($expiresInSeconds) {
            $token->expiresAt = new \DateTimeImmutable("+{$expiresInSeconds} seconds");
        }

        return $token;
    }

    /**
     * Get plain token (only available once at creation)
     */
    public function getPlainToken(): ?string
    {
        return $this->plainToken;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return TokenScopes::grants($this->scopes, $scope);
    }

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getLastUsedIp(): ?string
    {
        return $this->lastUsedIp;
    }

    public function recordUsage(string $ip): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->lastUsedIp = $ip;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

---

## Step 4: Token Repository

```php
<?php

declare(strict_types=1);

namespace App\Domain\ApiToken;

use Luminor\Database\Connection;

final class ApiTokenRepository
{
    public function __construct(
        private Connection $db,
    ) {}

    public function find(string $id): ?ApiToken
    {
        $row = $this->db->table('api_tokens')
            ->where('id', $id)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByHash(string $hash): ?ApiToken
    {
        $row = $this->db->table('api_tokens')
            ->where('token_hash', $hash)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPlainToken(string $plainToken): ?ApiToken
    {
        $hash = hash('sha256', $plainToken);
        return $this->findByHash($hash);
    }

    public function findAllForUser(string $userId): array
    {
        $rows = $this->db->table('api_tokens')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ApiToken $token): void
    {
        $data = [
            'id' => $token->getId(),
            'user_id' => $token->getUserId(),
            'name' => $token->getName(),
            'token_hash' => $token->getTokenHash(),
            'scopes' => json_encode($token->getScopes()),
            'last_used_ip' => $token->getLastUsedIp(),
            'last_used_at' => $token->getLastUsedAt()?->format('Y-m-d H:i:s'),
            'expires_at' => $token->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => $token->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->db->table('api_tokens')->upsert($data, ['id']);
    }

    public function delete(string $id): void
    {
        $this->db->table('api_tokens')
            ->where('id', $id)
            ->delete();
    }

    public function deleteAllForUser(string $userId): int
    {
        return $this->db->table('api_tokens')
            ->where('user_id', $userId)
            ->delete();
    }

    public function deleteExpired(): int
    {
        return $this->db->table('api_tokens')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->whereNotNull('expires_at')
            ->delete();
    }

    private function hydrate(array $row): ApiToken
    {
        // Use reflection or a factory to hydrate the entity
        $token = new ApiToken($row['id']);

        // Hydrate private properties (using reflection in production)
        $reflection = new \ReflectionClass($token);

        $this->setProperty($reflection, $token, 'userId', $row['user_id']);
        $this->setProperty($reflection, $token, 'name', $row['name']);
        $this->setProperty($reflection, $token, 'tokenHash', $row['token_hash']);
        $this->setProperty($reflection, $token, 'scopes', json_decode($row['scopes'] ?? '[]', true));
        $this->setProperty($reflection, $token, 'lastUsedIp', $row['last_used_ip']);
        $this->setProperty($reflection, $token, 'lastUsedAt',
            $row['last_used_at'] ? new \DateTimeImmutable($row['last_used_at']) : null);
        $this->setProperty($reflection, $token, 'expiresAt',
            $row['expires_at'] ? new \DateTimeImmutable($row['expires_at']) : null);
        $this->setProperty($reflection, $token, 'createdAt',
            new \DateTimeImmutable($row['created_at']));

        return $token;
    }

    private function setProperty(\ReflectionClass $reflection, object $object, string $name, mixed $value): void
    {
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
```

---

## Step 5: Token Service

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Domain\ApiToken\ApiToken;
use App\Domain\ApiToken\ApiTokenRepository;
use Luminor\Auth\AuthenticationException;

final class ApiTokenService
{
    public function __construct(
        private ApiTokenRepository $repository,
    ) {}

    /**
     * Create a new API token
     */
    public function create(
        string $userId,
        string $name,
        array $scopes = [],
        ?int $expiresInDays = null,
    ): ApiToken {
        // Validate scopes
        if (!empty($scopes) && !TokenScopes::validate($scopes)) {
            throw new \InvalidArgumentException('Invalid scopes provided');
        }

        $expiresInSeconds = $expiresInDays ? $expiresInDays * 86400 : null;

        $token = ApiToken::create(
            userId: $userId,
            name: $name,
            scopes: $scopes,
            expiresInSeconds: $expiresInSeconds
        );

        $this->repository->save($token);

        return $token;
    }

    /**
     * Validate a token and return the API token entity
     */
    public function validate(string $plainToken): ApiToken
    {
        $token = $this->repository->findByPlainToken($plainToken);

        if (!$token) {
            throw new AuthenticationException('Invalid API token');
        }

        if ($token->isExpired()) {
            throw new AuthenticationException('API token has expired');
        }

        return $token;
    }

    /**
     * Record token usage
     */
    public function recordUsage(ApiToken $token, string $ip): void
    {
        $token->recordUsage($ip);
        $this->repository->save($token);
    }

    /**
     * Get all tokens for a user
     */
    public function getTokensForUser(string $userId): array
    {
        return $this->repository->findAllForUser($userId);
    }

    /**
     * Revoke a specific token
     */
    public function revoke(string $tokenId, string $userId): void
    {
        $token = $this->repository->find($tokenId);

        if (!$token || $token->getUserId() !== $userId) {
            throw new AuthenticationException('Token not found');
        }

        $this->repository->delete($tokenId);
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAll(string $userId): int
    {
        return $this->repository->deleteAllForUser($userId);
    }

    /**
     * Clean up expired tokens (run in cron job)
     */
    public function cleanupExpired(): int
    {
        return $this->repository->deleteExpired();
    }
}
```

---

## Step 6: Authentication Provider

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Luminor\Auth\AuthenticationProvider;
use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;
use App\Domain\User\UserRepository;
use Utopia\Http\Request;

final class ApiTokenAuthenticationProvider implements AuthenticationProvider
{
    public function __construct(
        private ApiTokenService $tokenService,
        private UserRepository $userRepository,
    ) {}

    /**
     * Check if this provider supports the request
     */
    public function supports(Request $request): bool
    {
        return $this->extractToken($request) !== null;
    }

    /**
     * Authenticate the request
     */
    public function authenticate(Request $request): ?AuthenticatableInterface
    {
        $plainToken = $this->extractToken($request);

        if (!$plainToken) {
            return null;
        }

        try {
            $apiToken = $this->tokenService->validate($plainToken);

            // Record usage
            $this->tokenService->recordUsage($apiToken, $request->getIP() ?? 'unknown');

            // Get the user
            $user = $this->userRepository->find($apiToken->getUserId());

            if (!$user) {
                throw new AuthenticationException('User not found');
            }

            // Attach scopes to request for later use
            $request->setParam('_api_token_scopes', $apiToken->getScopes());
            $request->setParam('_api_token_id', $apiToken->getId());

            return $user;

        } catch (AuthenticationException $e) {
            throw $e;
        }
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization header
        $auth = $request->getHeader('Authorization');

        if ($auth && str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);

            // Distinguish from JWT (JWTs have 2 dots)
            if (substr_count($token, '.') !== 2) {
                return $token;
            }
        }

        // Check X-API-Token header
        $apiToken = $request->getHeader('X-API-Token');

        if ($apiToken) {
            return $apiToken;
        }

        // Check query parameter (not recommended for production)
        $queryToken = $request->getParam('api_token');

        if ($queryToken && $_ENV['ALLOW_TOKEN_IN_QUERY'] ?? false) {
            return $queryToken;
        }

        return null;
    }
}
```

---

## Step 7: Token Management Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\DDD\Infrastructure\Http\ApiController;
use Luminor\Auth\CurrentUser;
use App\Auth\ApiTokenService;
use App\Auth\TokenScopes;
use Utopia\Http\Request;
use Utopia\Http\Response;

final class ApiTokenController extends ApiController
{
    public function __construct(
        private ApiTokenService $tokenService,
    ) {}

    /**
     * GET /api/tokens
     * List all tokens for current user
     */
    public function index(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();
        $tokens = $this->tokenService->getTokensForUser($user->getId());

        $data = array_map(fn($token) => [
            'id' => $token->getId(),
            'name' => $token->getName(),
            'scopes' => $token->getScopes(),
            'last_used_at' => $token->getLastUsedAt()?->format('c'),
            'last_used_ip' => $token->getLastUsedIp(),
            'expires_at' => $token->getExpiresAt()?->format('c'),
            'created_at' => $token->getCreatedAt()->format('c'),
        ], $tokens);

        return $this->success($response, ['tokens' => $data]);
    }

    /**
     * GET /api/tokens/scopes
     * Get available scopes
     */
    public function scopes(Request $request, Response $response): Response
    {
        $scopes = [];

        foreach (TokenScopes::all() as $scope => $description) {
            $scopes[] = [
                'value' => $scope,
                'label' => $description,
            ];
        }

        return $this->success($response, ['scopes' => $scopes]);
    }

    /**
     * POST /api/tokens
     * Create a new token
     */
    public function store(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'name' => 'required|string|min:1|max:100',
            'scopes' => 'array',
            'expires_in_days' => 'integer|min:1|max:365',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $user = CurrentUser::get();

        try {
            $token = $this->tokenService->create(
                userId: $user->getId(),
                name: $payload['name'],
                scopes: $payload['scopes'] ?? [],
                expiresInDays: $payload['expires_in_days'] ?? null
            );

            // Include plain token in response (only shown once!)
            return $this->created($response, [
                'message' => 'Token created successfully. Save this token - it will not be shown again.',
                'token' => [
                    'id' => $token->getId(),
                    'name' => $token->getName(),
                    'token' => $token->getPlainToken(), // Only time this is available!
                    'scopes' => $token->getScopes(),
                    'expires_at' => $token->getExpiresAt()?->format('c'),
                    'created_at' => $token->getCreatedAt()->format('c'),
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/tokens/{id}
     * Revoke a specific token
     */
    public function destroy(Request $request, Response $response, string $id): Response
    {
        $user = CurrentUser::get();

        try {
            $this->tokenService->revoke($id, $user->getId());

            return $this->success($response, ['message' => 'Token revoked']);

        } catch (\Exception $e) {
            return $this->error($response, 'Token not found', 404);
        }
    }

    /**
     * DELETE /api/tokens
     * Revoke all tokens
     */
    public function destroyAll(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();

        $count = $this->tokenService->revokeAll($user->getId());

        return $this->success($response, [
            'message' => "{$count} tokens revoked",
        ]);
    }
}
```

---

## Step 8: Scope Checking Middleware

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Luminor\Auth\AuthorizationException;
use App\Auth\TokenScopes;
use Utopia\Http\Request;
use Utopia\Http\Response;

final class RequireScope
{
    public function __construct(
        private string|array $requiredScopes,
    ) {
        if (is_string($this->requiredScopes)) {
            $this->requiredScopes = [$this->requiredScopes];
        }
    }

    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        $tokenScopes = $request->getParam('_api_token_scopes', []);

        // If no API token is used, allow (other auth methods)
        if (empty($tokenScopes) && !$request->getParam('_api_token_id')) {
            return $next($request, $response);
        }

        // Check required scopes
        foreach ($this->requiredScopes as $required) {
            if (!TokenScopes::grants($tokenScopes, $required)) {
                throw new AuthorizationException(
                    "Missing required scope: {$required}"
                );
            }
        }

        return $next($request, $response);
    }
}
```

Use in routes:

```php
// Require specific scopes
$router->get('/api/users', [UserController::class, 'index'])
    ->middleware(new RequireScope('users:read'));

$router->post('/api/users', [UserController::class, 'store'])
    ->middleware(new RequireScope(['users:read', 'users:write']));

$router->delete('/api/users/{id}', [UserController::class, 'destroy'])
    ->middleware(new RequireScope('users:delete'));
```

---

## Step 9: Routes Configuration

```php
<?php

// routes/api.php

use App\Http\Controllers\ApiTokenController;
use App\Http\Middleware\RequireScope;

// Token management routes (require authentication)
$router->group(['prefix' => '/api/tokens', 'middleware' => $authMiddleware], function ($router) {
    $router->get('/', [ApiTokenController::class, 'index']);
    $router->get('/scopes', [ApiTokenController::class, 'scopes']);
    $router->post('/', [ApiTokenController::class, 'store']);
    $router->delete('/{id}', [ApiTokenController::class, 'destroy']);
    $router->delete('/', [ApiTokenController::class, 'destroyAll']);
});
```

---

## Step 10: Using API Tokens

### Creating a Token via API

```bash
# First, authenticate to get a session/JWT
curl -X POST https://api.yourapp.com/api/tokens \
  -H "Authorization: Bearer <your-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "CI/CD Pipeline",
    "scopes": ["posts:read", "posts:write"],
    "expires_in_days": 90
  }'
```

**Response:**
```json
{
  "message": "Token created successfully. Save this token - it will not be shown again.",
  "token": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "CI/CD Pipeline",
    "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6...",
    "scopes": ["posts:read", "posts:write"],
    "expires_at": "2025-03-15T00:00:00+00:00",
    "created_at": "2024-12-15T10:30:00+00:00"
  }
}
```

### Using the Token

```bash
# Using Bearer token
curl https://api.yourapp.com/api/posts \
  -H "Authorization: Bearer a1b2c3d4e5f6..."

# Using X-API-Token header
curl https://api.yourapp.com/api/posts \
  -H "X-API-Token: a1b2c3d4e5f6..."
```

### Token Management UI

```html
<!-- Token creation form -->
<form id="create-token-form">
    <div class="form-group">
        <label>Token Name</label>
        <input type="text" name="name" placeholder="e.g., CI/CD Pipeline" required>
    </div>

    <div class="form-group">
        <label>Scopes</label>
        <div id="scopes-container"></div>
    </div>

    <div class="form-group">
        <label>Expiration</label>
        <select name="expires_in_days">
            <option value="">Never</option>
            <option value="30">30 days</option>
            <option value="90">90 days</option>
            <option value="365">1 year</option>
        </select>
    </div>

    <button type="submit">Create Token</button>
</form>

<!-- Token display modal -->
<div id="token-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2>Token Created</h2>
        <p class="warning">Copy this token now. It will not be shown again!</p>
        <div class="token-display">
            <code id="token-value"></code>
            <button onclick="copyToken()">Copy</button>
        </div>
        <button onclick="closeModal()">I've Copied the Token</button>
    </div>
</div>

<script>
// Load available scopes
async function loadScopes() {
    const response = await fetch('/api/tokens/scopes', {
        headers: { 'Authorization': `Bearer ${getAccessToken()}` }
    });
    const { scopes } = await response.json();

    const container = document.getElementById('scopes-container');
    scopes.forEach(scope => {
        container.innerHTML += `
            <label class="checkbox">
                <input type="checkbox" name="scopes[]" value="${scope.value}">
                ${scope.label}
            </label>
        `;
    });
}

// Create token
document.getElementById('create-token-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const scopes = formData.getAll('scopes[]');

    const response = await fetch('/api/tokens', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${getAccessToken()}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            name: formData.get('name'),
            scopes: scopes,
            expires_in_days: formData.get('expires_in_days') || null
        })
    });

    const data = await response.json();

    if (response.ok) {
        // Show token in modal
        document.getElementById('token-value').textContent = data.token.token;
        document.getElementById('token-modal').style.display = 'flex';
        loadTokens(); // Refresh token list
    } else {
        alert('Error: ' + data.message);
    }
});

function copyToken() {
    const token = document.getElementById('token-value').textContent;
    navigator.clipboard.writeText(token);
    alert('Token copied to clipboard!');
}

loadScopes();
</script>
```

---

## Step 11: Cleanup Cron Job

Set up a cron job to clean expired tokens:

```bash
# crontab -e
0 0 * * * php /path/to/your/app/artisan tokens:cleanup
```

Or create a cleanup command:

```php
<?php

// commands/CleanupTokensCommand.php
namespace App\Commands;

use App\Auth\ApiTokenService;

final class CleanupTokensCommand
{
    public function __construct(
        private ApiTokenService $tokenService,
    ) {}

    public function __invoke(): int
    {
        $count = $this->tokenService->cleanupExpired();

        echo "Cleaned up {$count} expired tokens.\n";

        return 0;
    }
}
```

---

## Security Best Practices

1. **Hash tokens before storage** - Never store plain tokens
2. **Show tokens only once** - Token is only visible at creation
3. **Implement expiration** - Tokens should have reasonable lifetimes
4. **Use HTTPS only** - Never transmit tokens over HTTP
5. **Implement scopes** - Principle of least privilege
6. **Log token usage** - Audit trail for security
7. **Allow revocation** - Users should be able to revoke tokens
8. **Rate limit** - Prevent brute force attacks

---

## Next Steps

- [Implement JWT for Session Authentication](./jwt-authentication.md)
- [Add Multi-Factor Authentication](./mfa-authentication.md)
- [Set Up Authorization Policies](./authorization-rbac.md)
