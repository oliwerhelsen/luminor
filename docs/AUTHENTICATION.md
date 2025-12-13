---
title: Authentication
layout: default
nav_order: 10
has_children: true
description: "Enterprise-grade authentication with JWT, OpenID Connect, sessions, API tokens, and MFA"
permalink: /authentication/
---

# Enterprise Authentication Guide

Luminor provides comprehensive, enterprise-grade authentication with support for multiple authentication methods:

- **JWT Bearer Tokens** - Stateless API authentication
- **OpenID Connect (OIDC)** - Single Sign-On with Azure AD, Google, Okta, etc.
- **Session-based** - Traditional web application authentication
- **API Tokens** - Long-lived tokens for service accounts and integrations
- **Multi-Factor Authentication (MFA)** - TOTP-based 2FA
- **Rate Limiting** - Protection against brute force attacks

## Table of Contents

1. [Quick Start](#quick-start)
2. [JWT Authentication](#jwt-authentication)
3. [OpenID Connect (SSO)](#openid-connect-sso)
4. [Session Authentication](#session-authentication)
5. [API Tokens](#api-tokens)
6. [Multi-Factor Authentication](#multi-factor-authentication)
7. [Rate Limiting](#rate-limiting)
8. [Configuration](#configuration)
9. [Security Best Practices](#security-best-practices)

---

## Quick Start

### 1. Register the Authentication Service Provider

```php
// bootstrap/app.php or config/services.php
use Luminor\Auth\AuthServiceProvider;

$container->register(new AuthServiceProvider());
```

### 2. Configure Environment Variables

```bash
# JWT Configuration
JWT_SECRET="your-very-secure-secret-key-minimum-32-characters"
JWT_TTL=3600
JWT_ISSUER="your-app-name"

# Rate Limiting
RATE_LIMIT_MAX_ATTEMPTS=5
RATE_LIMIT_DECAY_SECONDS=60

# OpenID Connect (Optional - for SSO)
AZURE_TENANT_ID="your-tenant-id"
AZURE_CLIENT_ID="your-client-id"
AZURE_CLIENT_SECRET="your-client-secret"
AZURE_REDIRECT_URI="https://yourapp.com/auth/callback"
```

### 3. Set Up Authentication Middleware

```php
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Jwt\JwtAuthenticationProvider;
use Luminor\Infrastructure\Http\Middleware\AuthenticationMiddleware;

// Set up authentication manager
$authManager = $container->get(AuthenticationManager::class);
$jwtService = $container->get(JwtService::class);

// Register JWT provider
$jwtProvider = new JwtAuthenticationProvider(
    $jwtService,
    function ($userId) {
        // Resolve user from database
        return User::find($userId);
    }
);

$authManager->register($jwtProvider);

// Apply middleware to routes
$router->middleware(new AuthenticationMiddleware($authManager));
```

---

## JWT Authentication

JWT (JSON Web Tokens) provide stateless authentication perfect for APIs and microservices.

### Generating JWT Tokens

```php
use Luminor\Auth\Jwt\JwtService;

$jwtService = $container->get(JwtService::class);

// Generate access token
$token = $jwtService->generate($user->id, [
    'email' => $user->email,
    'role' => $user->role,
]);

// Generate refresh token
$refreshToken = $jwtService->generateRefreshToken($user->id);

// Return to client
return Response::json([
    'access_token' => $token->getToken(),
    'refresh_token' => $refreshToken->getToken(),
    'expires_in' => $token->getTimeToExpiry(),
    'token_type' => 'Bearer',
]);
```

### Client Usage

Clients include the JWT token in the Authorization header:

```bash
curl -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
     https://api.yourapp.com/user/profile
```

### Validating JWT Tokens

The `JwtAuthenticationProvider` automatically validates tokens. Access the authenticated user:

```php
use Luminor\Auth\CurrentUser;

$user = CurrentUser::get();
echo $user->email;
```

### Token Refresh Flow

```php
// Refresh endpoint
public function refresh(Request $request): Response
{
    $refreshToken = $request->input('refresh_token');

    try {
        $jwtToken = $this->jwtService->parse($refreshToken);

        if ($jwtToken->getClaim('type') !== 'refresh') {
            throw new Exception('Invalid token type');
        }

        $userId = $jwtToken->getSubject();
        $user = User::find($userId);

        // Generate new access token
        $newToken = $this->jwtService->generate($user->id, [
            'email' => $user->email,
        ]);

        return Response::json([
            'access_token' => $newToken->getToken(),
            'expires_in' => $newToken->getTimeToExpiry(),
        ]);

    } catch (AuthenticationException $e) {
        return Response::json(['error' => 'Invalid refresh token'], 401);
    }
}
```

---

## OpenID Connect (SSO)

OpenID Connect enables Single Sign-On with enterprise identity providers.

### Supported Providers

- Azure Active Directory (Microsoft 365)
- Google Workspace
- Okta
- Any OIDC-compliant provider

### Configuration

#### Azure AD

```bash
AZURE_TENANT_ID="12345678-1234-1234-1234-123456789abc"
AZURE_CLIENT_ID="your-client-id"
AZURE_CLIENT_SECRET="your-client-secret"
AZURE_REDIRECT_URI="https://yourapp.com/auth/azure/callback"
```

#### Google

```bash
GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="your-client-secret"
GOOGLE_REDIRECT_URI="https://yourapp.com/auth/google/callback"
```

### Implementation

```php
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Auth\OpenId\OpenIdAuthenticationProvider;

// Create OIDC provider
$oidcProvider = OpenIdProvider::azure(
    $_ENV['AZURE_TENANT_ID'],
    $_ENV['AZURE_CLIENT_ID'],
    $_ENV['AZURE_CLIENT_SECRET'],
    $_ENV['AZURE_REDIRECT_URI']
);

$oidcService = new OpenIdService($oidcProvider);

// Register provider
$authManager->register(new OpenIdAuthenticationProvider(
    $oidcService,
    function (array $claims, array $tokens) {
        // Find or create user from OIDC claims
        $user = User::firstOrCreate(
            ['email' => $claims['email']],
            [
                'name' => $claims['name'] ?? '',
                'email_verified' => $claims['email_verified'] ?? false,
            ]
        );

        // Optionally store OIDC tokens
        $user->oidc_access_token = $tokens['access_token'];
        $user->oidc_refresh_token = $tokens['refresh_token'] ?? null;
        $user->save();

        return $user;
    }
));
```

### Login Flow

```php
// Step 1: Redirect to IDP
public function redirectToProvider(): Response
{
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));

    // Store state in session for verification
    $_SESSION['oidc_state'] = $state;
    $_SESSION['oidc_nonce'] = $nonce;

    $authUrl = $this->oidcService->getAuthorizationUrl($state, $nonce);

    return Response::redirect($authUrl);
}

// Step 2: Handle callback
public function handleCallback(Request $request): Response
{
    $state = $request->query('state');

    // Verify state to prevent CSRF
    if (!hash_equals($_SESSION['oidc_state'], $state)) {
        return Response::json(['error' => 'Invalid state'], 400);
    }

    // Authentication happens automatically via OpenIdAuthenticationProvider
    $user = CurrentUser::get();

    // Clear OIDC session data
    unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);

    return Response::redirect('/dashboard');
}
```

---

## Session Authentication

Traditional session-based authentication for web applications.

### Setup

```php
use Luminor\Auth\Session\SessionAuthenticationProvider;
use Luminor\Session\Session;

$session = $container->get(Session::class);

$sessionAuth = new SessionAuthenticationProvider(
    $session,
    function ($identifier, $byEmail = false) {
        if ($byEmail) {
            return User::where('email', $identifier)->first();
        }
        return User::find($identifier);
    }
);

$authManager->register($sessionAuth);
```

### Login

```php
public function login(Request $request): Response
{
    $credentials = [
        'email' => $request->input('email'),
        'password' => $request->input('password'),
    ];

    $remember = $request->input('remember') === 'true';

    try {
        $user = $this->sessionAuth->attempt($credentials, $remember);

        return Response::json([
            'message' => 'Login successful',
            'user' => $user,
        ]);

    } catch (AuthenticationException $e) {
        return Response::json(['error' => $e->getMessage()], 401);
    }
}
```

### Logout

```php
public function logout(): Response
{
    $this->sessionAuth->logout();

    return Response::json(['message' => 'Logged out successfully']);
}
```

### Manual Login (without password check)

```php
$user = User::find(123);
$this->sessionAuth->login($user, $remember = true);
```

---

## API Tokens

Long-lived tokens for service accounts, CLI tools, and third-party integrations.

### Creating API Tokens

```php
use Luminor\Auth\ApiToken\ApiTokenManager;

$tokenManager = $container->get(ApiTokenManager::class);

// Create token for user
$apiToken = $tokenManager->create(
    $user,
    name: 'Production API',
    scopes: ['users:read', 'posts:write'],
    expiresInSeconds: 86400 * 365 // 1 year
);

// Return token to user (only shown once!)
return Response::json([
    'token' => $apiToken->getToken(), // e.g., "a1b2c3d4e5f6..."
    'name' => $apiToken->getName(),
    'scopes' => $apiToken->getScopes(),
    'expires_at' => $apiToken->getExpiresAt(),
]);
```

### Using API Tokens

```bash
curl -H "Authorization: Bearer a1b2c3d4e5f6..." \
     https://api.yourapp.com/users
```

or

```bash
curl -H "X-API-Token: a1b2c3d4e5f6..." \
     https://api.yourapp.com/users
```

### Managing Tokens

```php
// List user's tokens
$tokens = $tokenManager->getAllForUser($user->id);

// Revoke specific token
$tokenManager->revoke($tokenId);

// Revoke all user tokens
$tokenManager->revokeAllForUser($user->id);

// Cleanup expired tokens (run in cron job)
$deleted = $tokenManager->cleanupExpired();
```

### Checking Token Scopes

```php
use Luminor\Http\Request;

$scopes = $request->attributes['api_token_scopes'] ?? [];

if (!in_array('users:write', $scopes)) {
    return Response::json(['error' => 'Insufficient permissions'], 403);
}
```

---

## Multi-Factor Authentication

TOTP-based two-factor authentication compatible with Google Authenticator, Authy, etc.

### Enabling MFA

```php
use Luminor\Auth\Mfa\MfaService;

$mfaService = $container->get(MfaService::class);

// Step 1: Generate MFA secret
$mfaData = $mfaService->enable($user);

// Return QR code to user
return Response::json([
    'secret' => $mfaData['secret'],
    'qr_code_uri' => $mfaData['qr_code_uri'],
    'recovery_codes' => $mfaData['recovery_codes'],
]);

// Step 2: User scans QR code and enters verification code
$code = $request->input('code');

try {
    $mfaService->confirm($user, $code);
    return Response::json(['message' => 'MFA enabled successfully']);
} catch (AuthenticationException $e) {
    return Response::json(['error' => 'Invalid code'], 400);
}
```

### Verifying MFA During Login

```php
public function login(Request $request): Response
{
    // Authenticate user credentials first
    $user = $this->sessionAuth->attempt([
        'email' => $request->input('email'),
        'password' => $request->input('password'),
    ]);

    // Check if MFA is enabled
    if ($this->mfaService->isEnabled($user)) {
        // Require MFA code
        $mfaCode = $request->input('mfa_code');

        if (!$mfaCode || !$this->mfaService->verify($user, $mfaCode)) {
            return Response::json([
                'error' => 'MFA code required or invalid',
                'requires_mfa' => true,
            ], 401);
        }
    }

    return Response::json(['message' => 'Login successful']);
}
```

### Recovery Codes

```php
// Regenerate recovery codes
$newCodes = $mfaService->regenerateRecoveryCodes($user);

return Response::json([
    'recovery_codes' => $newCodes,
    'message' => 'Save these codes in a secure location',
]);
```

### Disabling MFA

```php
$mfaService->disable($user);
```

---

## Rate Limiting

Protect authentication endpoints from brute force attacks.

### Applying Rate Limiting

```php
use Luminor\Auth\RateLimit\RateLimitMiddleware;
use Luminor\Auth\RateLimit\RateLimiter;

$rateLimiter = $container->get(RateLimiter::class);

// Apply to login endpoint (5 attempts per minute)
$router->post('/login', [AuthController::class, 'login'])
    ->middleware(new RateLimitMiddleware($rateLimiter, 5));

// Apply to API routes (60 requests per minute)
$router->group(['prefix' => '/api'], function ($router) {
    // Routes here...
})->middleware(new RateLimitMiddleware($rateLimiter, 60));
```

### Custom Rate Limiting Logic

```php
public function login(Request $request): Response
{
    $key = 'login:' . $request->ip();

    if ($this->rateLimiter->tooManyAttempts($key, 5)) {
        $retryAfter = $this->rateLimiter->availableIn($key);

        return Response::json([
            'error' => 'Too many login attempts',
            'retry_after' => $retryAfter,
        ], 429);
    }

    try {
        $user = $this->sessionAuth->attempt($credentials);

        // Clear rate limit on successful login
        $this->rateLimiter->clear($key);

        return Response::json(['message' => 'Login successful']);

    } catch (AuthenticationException $e) {
        // Increment failed attempts
        $this->rateLimiter->hit($key);

        return Response::json(['error' => 'Invalid credentials'], 401);
    }
}
```

---

## Configuration

### Environment Variables

```bash
# JWT Configuration
JWT_SECRET="minimum-32-characters-secret-key"
JWT_TTL=3600                    # Token lifetime in seconds
JWT_ISSUER="your-app-name"

# Rate Limiting
RATE_LIMIT_MAX_ATTEMPTS=5       # Max attempts before blocking
RATE_LIMIT_DECAY_SECONDS=60     # Time window in seconds

# Azure AD (OpenID Connect)
AZURE_TENANT_ID="tenant-id"
AZURE_CLIENT_ID="client-id"
AZURE_CLIENT_SECRET="client-secret"
AZURE_REDIRECT_URI="https://yourapp.com/auth/callback"

# Google (OpenID Connect)
GOOGLE_CLIENT_ID="client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="client-secret"
GOOGLE_REDIRECT_URI="https://yourapp.com/auth/google/callback"

# Okta (OpenID Connect)
OKTA_DOMAIN="your-domain.okta.com"
OKTA_CLIENT_ID="client-id"
OKTA_CLIENT_SECRET="client-secret"
OKTA_REDIRECT_URI="https://yourapp.com/auth/okta/callback"

# Custom OIDC Provider
CUSTOM_NAME="custom-provider"
CUSTOM_CLIENT_ID="client-id"
CUSTOM_CLIENT_SECRET="client-secret"
CUSTOM_DISCOVERY_URL="https://provider.com/.well-known/openid-configuration"
CUSTOM_REDIRECT_URI="https://yourapp.com/auth/custom/callback"
```

### Multiple Authentication Methods

You can use multiple authentication methods simultaneously:

```php
// Register all providers
$authManager
    ->register($jwtProvider)
    ->register($sessionProvider)
    ->register($oidcProvider)
    ->register($apiTokenProvider);

// Authentication will try each provider in order
// JWT and API tokens are checked by request headers
// Session is checked by session data
// OIDC is checked by authorization code parameter
```

---

## Security Best Practices

### 1. Secure JWT Secrets

```bash
# Generate a strong secret:
php -r "echo bin2hex(random_bytes(32));"
```

Store in `.env` and never commit to version control.

### 2. Use HTTPS

Always use HTTPS in production to prevent token interception.

### 3. Set Appropriate Token Expiration

```php
// Short-lived access tokens
$jwtService = new JwtService($secret, ttl: 900); // 15 minutes

// Long-lived refresh tokens
$refreshToken = $jwtService->generateRefreshToken($userId); // 30 days
```

### 4. Implement Token Rotation

Rotate refresh tokens on each use:

```php
public function refresh(Request $request): Response
{
    $oldRefreshToken = $request->input('refresh_token');
    $jwtToken = $this->jwtService->parse($oldRefreshToken);

    $userId = $jwtToken->getSubject();

    // Generate new tokens
    $newAccessToken = $this->jwtService->generate($userId);
    $newRefreshToken = $this->jwtService->generateRefreshToken($userId);

    // Blacklist old refresh token (requires implementation)

    return Response::json([
        'access_token' => $newAccessToken->getToken(),
        'refresh_token' => $newRefreshToken->getToken(),
    ]);
}
```

### 5. Enable MFA for Sensitive Accounts

Require MFA for admin accounts and sensitive operations.

### 6. Rate Limit Authentication Endpoints

Always apply rate limiting to login, registration, and password reset endpoints.

### 7. Validate OIDC State Parameter

Always verify the state parameter to prevent CSRF attacks:

```php
if (!hash_equals($_SESSION['oidc_state'], $request->query('state'))) {
    throw new Exception('Invalid state');
}
```

### 8. Store API Tokens Securely

- Never log API tokens
- Hash tokens before storing in database
- Display tokens only once upon creation
- Provide token revocation mechanism

### 9. Implement Account Lockout

Lock accounts after multiple failed login attempts:

```php
if ($this->rateLimiter->tooManyAttempts("login:{$email}", 5)) {
    // Lock account for 30 minutes
    User::where('email', $email)->update(['locked_until' => time() + 1800]);
}
```

### 10. Regular Security Audits

- Review active sessions and API tokens regularly
- Implement token cleanup for expired tokens
- Monitor authentication logs for suspicious activity
- Keep dependencies updated

---

## Complete Example

Here's a complete authentication setup:

```php
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Jwt\JwtAuthenticationProvider;
use Luminor\Auth\Session\SessionAuthenticationProvider;
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Auth\OpenId\OpenIdAuthenticationProvider;
use Luminor\Auth\ApiToken\ApiTokenManager;
use Luminor\Auth\ApiToken\ApiTokenAuthenticationProvider;
use Luminor\Auth\Mfa\MfaService;
use Luminor\Infrastructure\Http\Middleware\AuthenticationMiddleware;

// Initialize services
$authManager = new AuthenticationManager();
$jwtService = new JwtService($_ENV['JWT_SECRET'], 3600, $_ENV['JWT_ISSUER']);
$session = $container->get(Session::class);
$apiTokenManager = $container->get(ApiTokenManager::class);
$mfaService = $container->get(MfaService::class);

// User resolver function
$userResolver = function ($identifier, $byEmail = false) {
    if ($byEmail) {
        return User::where('email', $identifier)->first();
    }
    return User::find($identifier);
};

// Register JWT Provider
$authManager->register(new JwtAuthenticationProvider($jwtService, $userResolver));

// Register Session Provider
$authManager->register(new SessionAuthenticationProvider($session, $userResolver));

// Register API Token Provider
$authManager->register(new ApiTokenAuthenticationProvider($apiTokenManager, $userResolver));

// Register OIDC Provider (if configured)
if ($oidcProvider = AuthServiceProvider::createOidcProvider('azure')) {
    $oidcService = new OpenIdService($oidcProvider);
    $authManager->register(new OpenIdAuthenticationProvider(
        $oidcService,
        function ($claims, $tokens) use ($userResolver) {
            $user = User::firstOrCreate(
                ['email' => $claims['email']],
                ['name' => $claims['name'] ?? '']
            );
            return $user;
        }
    ));
}

// Apply authentication middleware
$router->middleware(new AuthenticationMiddleware($authManager, required: true, excludedRoutes: [
    '/login',
    '/register',
    '/auth/*',
]));
```

---

For more information, see the source code in `src/Auth/` or run the test suite in `tests/Auth/`.
