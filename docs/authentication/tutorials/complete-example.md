---
title: Complete Authentication Example
layout: default
parent: Authentication
nav_order: 6
description: "Complete, production-ready authentication setup combining all authentication methods"
---

# Complete Authentication Example

This guide provides a complete, production-ready authentication setup combining all authentication methods in a single application.

## Overview

This example demonstrates:

- JWT authentication for API endpoints
- Session authentication for web pages
- OpenID Connect SSO (Azure AD)
- API tokens for service accounts
- Multi-Factor Authentication
- Rate limiting
- Authorization policies

---

## Project Structure

```
app/
├── Auth/
│   ├── AuthService.php           # Main authentication service
│   ├── ApiTokenService.php       # API token management
│   ├── TokenScopes.php           # Token scope definitions
│   └── Mfa/
│       ├── MfaService.php        # MFA service
│       └── TotpService.php       # TOTP implementation
├── Domain/
│   └── User/
│       ├── User.php              # User entity
│       └── UserRepository.php    # User repository
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php    # Auth endpoints
│   │   ├── SsoController.php     # SSO endpoints
│   │   ├── MfaController.php     # MFA endpoints
│   │   └── ApiTokenController.php # Token management
│   └── Middleware/
│       ├── AuthMiddleware.php    # Authentication check
│       ├── GuestMiddleware.php   # Guest-only routes
│       └── RequireScope.php      # API scope check
├── Providers/
│   └── AuthServiceProvider.php   # Auth service registration
bootstrap/
├── app.php                       # Application bootstrap
config/
├── auth.php                      # Auth configuration
routes/
├── api.php                       # API routes
└── web.php                       # Web routes
```

---

## Step 1: Configuration

### Environment Variables (.env)

```bash
# Application
APP_NAME="My Application"
APP_ENV=production
APP_URL=https://myapp.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret

# JWT Configuration
JWT_SECRET="your-very-secure-secret-key-minimum-32-characters-long"
JWT_TTL=3600
JWT_REFRESH_TTL=604800
JWT_ISSUER="myapp"

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.myapp.com

# Rate Limiting
RATE_LIMIT_MAX_ATTEMPTS=5
RATE_LIMIT_DECAY_SECONDS=60

# Azure AD (SSO)
AZURE_TENANT_ID="your-tenant-id"
AZURE_CLIENT_ID="your-client-id"
AZURE_CLIENT_SECRET="your-client-secret"
AZURE_REDIRECT_URI="https://myapp.com/auth/azure/callback"

# Google (SSO)
GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="your-client-secret"
GOOGLE_REDIRECT_URI="https://myapp.com/auth/google/callback"

# Mail (for verification/reset emails)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=username
MAIL_PASSWORD=password
MAIL_FROM_ADDRESS=noreply@myapp.com
MAIL_FROM_NAME="My Application"
```

### Auth Configuration (config/auth.php)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => 'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
        ],
        'api' => [
            'driver' => 'jwt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    */
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => env('JWT_TTL', 3600),
        'refresh_ttl' => env('JWT_REFRESH_TTL', 604800),
        'issuer' => env('JWT_ISSUER', 'luminor'),
        'algorithm' => 'HS256',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth/OpenID Connect Providers
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'azure' => [
            'tenant_id' => env('AZURE_TENANT_ID'),
            'client_id' => env('AZURE_CLIENT_ID'),
            'client_secret' => env('AZURE_CLIENT_SECRET'),
            'redirect_uri' => env('AZURE_REDIRECT_URI'),
        ],
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'login' => [
            'max_attempts' => env('RATE_LIMIT_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        'api' => [
            'max_attempts' => 60,
            'decay_seconds' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Configuration
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_number' => true,
        'bcrypt_cost' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA Configuration
    |--------------------------------------------------------------------------
    */
    'mfa' => [
        'issuer' => env('APP_NAME', 'Luminor'),
        'recovery_codes_count' => 8,
    ],
];
```

---

## Step 2: Auth Service Provider

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Luminor\Container\ServiceProvider;
use Luminor\Container\Container;
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Jwt\JwtAuthenticationProvider;
use Luminor\Auth\Session\SessionAuthenticationProvider;
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Auth\OpenId\OpenIdAuthenticationProvider;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Auth\RateLimit\ArrayRateLimitStore;
use Luminor\Session\Session;
use App\Auth\ApiTokenService;
use App\Auth\ApiTokenAuthenticationProvider;
use App\Auth\Mfa\MfaService;
use App\Auth\Mfa\TotpService;
use App\Domain\User\UserRepository;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $config = require __DIR__ . '/../../config/auth.php';

        // Register JWT Service
        $container->singleton(JwtService::class, function () use ($config) {
            return new JwtService(
                secret: $config['jwt']['secret'],
                ttl: $config['jwt']['ttl'],
                issuer: $config['jwt']['issuer']
            );
        });

        // Register Rate Limiter
        $container->singleton(RateLimiter::class, function () use ($config) {
            return new RateLimiter(
                store: new ArrayRateLimitStore(),
                maxAttempts: $config['rate_limiting']['login']['max_attempts'],
                decaySeconds: $config['rate_limiting']['login']['decay_seconds']
            );
        });

        // Register TOTP Service
        $container->singleton(TotpService::class, function () {
            return new TotpService();
        });

        // Register MFA Service
        $container->singleton(MfaService::class, function ($c) {
            return new MfaService(
                $c->get(TotpService::class),
                $c->get(UserRepository::class)
            );
        });

        // Register API Token Service
        $container->singleton(ApiTokenService::class, function ($c) {
            return new ApiTokenService(
                $c->get(\App\Domain\ApiToken\ApiTokenRepository::class)
            );
        });

        // Register Authentication Manager with all providers
        $container->singleton(AuthenticationManager::class, function ($c) use ($config) {
            $authManager = new AuthenticationManager();

            $userRepository = $c->get(UserRepository::class);
            $userResolver = fn($id) => $userRepository->find($id);

            // 1. JWT Provider (for API)
            $jwtProvider = new JwtAuthenticationProvider(
                $c->get(JwtService::class),
                $userResolver
            );
            $authManager->register($jwtProvider);

            // 2. Session Provider (for web)
            $sessionProvider = new SessionAuthenticationProvider(
                $c->get(Session::class),
                fn($id, $byEmail) => $byEmail
                    ? $userRepository->findByEmail($id)
                    : $userRepository->find($id)
            );
            $authManager->register($sessionProvider);

            // 3. API Token Provider
            $apiTokenProvider = new ApiTokenAuthenticationProvider(
                $c->get(ApiTokenService::class),
                $userRepository
            );
            $authManager->register($apiTokenProvider);

            // 4. OpenID Connect Providers
            $this->registerOidcProviders($authManager, $config, $userRepository);

            return $authManager;
        });
    }

    private function registerOidcProviders(
        AuthenticationManager $authManager,
        array $config,
        UserRepository $userRepository
    ): void {
        // Azure AD
        if (!empty($config['oauth']['azure']['client_id'])) {
            $provider = OpenIdProvider::azure(
                tenantId: $config['oauth']['azure']['tenant_id'],
                clientId: $config['oauth']['azure']['client_id'],
                clientSecret: $config['oauth']['azure']['client_secret'],
                redirectUri: $config['oauth']['azure']['redirect_uri']
            );

            $oidcService = new OpenIdService($provider);

            $authManager->register(new OpenIdAuthenticationProvider(
                $oidcService,
                function (array $claims, array $tokens) use ($userRepository) {
                    return $this->findOrCreateOidcUser($userRepository, $claims, 'azure');
                }
            ));
        }

        // Google
        if (!empty($config['oauth']['google']['client_id'])) {
            $provider = OpenIdProvider::google(
                clientId: $config['oauth']['google']['client_id'],
                clientSecret: $config['oauth']['google']['client_secret'],
                redirectUri: $config['oauth']['google']['redirect_uri']
            );

            $oidcService = new OpenIdService($provider);

            $authManager->register(new OpenIdAuthenticationProvider(
                $oidcService,
                function (array $claims, array $tokens) use ($userRepository) {
                    return $this->findOrCreateOidcUser($userRepository, $claims, 'google');
                }
            ));
        }
    }

    private function findOrCreateOidcUser(
        UserRepository $userRepository,
        array $claims,
        string $provider
    ): \App\Domain\User\User {
        $email = $claims['email'] ?? $claims['preferred_username'];

        $user = $userRepository->findByEmail($email);

        if (!$user) {
            $user = \App\Domain\User\User::createFromOidc(
                name: $claims['name'] ?? $email,
                email: $email,
                provider: $provider,
                providerId: $claims['sub']
            );
            $userRepository->save($user);
        }

        return $user;
    }
}
```

---

## Step 3: Routes

### Web Routes (routes/web.php)

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\GuestMiddleware;
use Luminor\Auth\RateLimit\RateLimitMiddleware;

// Guest-only routes
$router->group(['middleware' => new GuestMiddleware()], function ($router) use ($rateLimiter) {
    // Login
    $router->get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    $router->post('/login', [AuthController::class, 'login'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 5));
    $router->post('/login/mfa', [AuthController::class, 'verifyMfa']);

    // Registration
    $router->get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    $router->post('/register', [AuthController::class, 'register'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 10));

    // Password Reset
    $router->get('/forgot-password', [AuthController::class, 'showForgotPasswordForm']);
    $router->post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 3));
    $router->get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);

    // SSO
    $router->get('/auth/{provider}', [SsoController::class, 'redirect']);
    $router->get('/auth/{provider}/callback', [SsoController::class, 'callback']);
});

// Email verification (accessible to anyone with token)
$router->get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);

// Authenticated routes
$router->group(['middleware' => new AuthMiddleware()], function ($router) {
    // Dashboard
    $router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Logout
    $router->post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Account Settings
    $router->get('/account', [AccountController::class, 'index']);
    $router->post('/account/password', [AccountController::class, 'updatePassword']);

    // MFA Management
    $router->get('/account/mfa', [MfaController::class, 'index']);
    $router->post('/account/mfa/setup', [MfaController::class, 'setup']);
    $router->post('/account/mfa/confirm', [MfaController::class, 'confirm']);
    $router->post('/account/mfa/disable', [MfaController::class, 'disable']);
    $router->post('/account/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes']);

    // API Token Management (Web UI)
    $router->get('/account/tokens', [ApiTokenController::class, 'index']);
    $router->post('/account/tokens', [ApiTokenController::class, 'store']);
    $router->delete('/account/tokens/{id}', [ApiTokenController::class, 'destroy']);
});
```

### API Routes (routes/api.php)

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PostController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Middleware\RequireScope;
use Luminor\Auth\RateLimit\RateLimitMiddleware;

// API prefix is /api

// Public API routes
$router->group(['prefix' => '/api'], function ($router) use ($rateLimiter) {
    // Auth
    $router->post('/auth/login', [AuthController::class, 'login'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 5));
    $router->post('/auth/register', [AuthController::class, 'register'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 10));
    $router->post('/auth/refresh', [AuthController::class, 'refresh']);
    $router->post('/auth/login/mfa', [AuthController::class, 'verifyMfa']);
});

// Protected API routes
$router->group([
    'prefix' => '/api',
    'middleware' => new ApiAuthMiddleware()
], function ($router) {
    // Current user
    $router->get('/auth/me', [AuthController::class, 'me']);
    $router->post('/auth/logout', [AuthController::class, 'logout']);

    // MFA
    $router->get('/auth/mfa/status', [AuthController::class, 'mfaStatus']);
    $router->post('/auth/mfa/setup', [AuthController::class, 'mfaSetup']);
    $router->post('/auth/mfa/confirm', [AuthController::class, 'mfaConfirm']);
    $router->post('/auth/mfa/disable', [AuthController::class, 'mfaDisable']);

    // API Tokens
    $router->get('/tokens', [TokenController::class, 'index']);
    $router->get('/tokens/scopes', [TokenController::class, 'scopes']);
    $router->post('/tokens', [TokenController::class, 'store']);
    $router->delete('/tokens/{id}', [TokenController::class, 'destroy']);

    // Users (with scope checks)
    $router->get('/users', [UserController::class, 'index'])
        ->middleware(new RequireScope('users:read'));
    $router->get('/users/{id}', [UserController::class, 'show'])
        ->middleware(new RequireScope('users:read'));
    $router->post('/users', [UserController::class, 'store'])
        ->middleware(new RequireScope('users:write'));
    $router->put('/users/{id}', [UserController::class, 'update'])
        ->middleware(new RequireScope('users:write'));
    $router->delete('/users/{id}', [UserController::class, 'destroy'])
        ->middleware(new RequireScope('users:delete'));

    // Posts (with scope checks)
    $router->get('/posts', [PostController::class, 'index'])
        ->middleware(new RequireScope('posts:read'));
    $router->post('/posts', [PostController::class, 'store'])
        ->middleware(new RequireScope('posts:write'));
    $router->put('/posts/{id}', [PostController::class, 'update'])
        ->middleware(new RequireScope('posts:write'));
    $router->delete('/posts/{id}', [PostController::class, 'destroy'])
        ->middleware(new RequireScope('posts:delete'));
});
```

---

## Step 4: Middleware

### Auth Middleware

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\CurrentUser;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class AuthMiddleware
{
    public function __construct(
        private AuthenticationManager $authManager,
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        try {
            $user = $this->authManager->authenticate($request);

            if (!$user) {
                return $this->unauthorized($response, $request);
            }

            CurrentUser::set($user);

            $response = $next($request, $response);

            CurrentUser::clear();

            return $response;

        } catch (\Exception $e) {
            return $this->unauthorized($response, $request);
        }
    }

    private function unauthorized(Response $response, Request $request): Response
    {
        // Check if it's an API request
        if ($this->isApiRequest($request)) {
            $response->setStatusCode(401);
            $response->json(['error' => 'Unauthorized']);
            return $response;
        }

        // Redirect to login for web requests
        $redirect = urlencode($request->getPath());
        $response->addHeader('Location', "/login?redirect={$redirect}");
        $response->setStatusCode(302);
        return $response;
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/api')
            || $request->getHeader('Accept') === 'application/json';
    }
}
```

### Guest Middleware

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Luminor\Auth\CurrentUser;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class GuestMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        if (CurrentUser::isAuthenticated()) {
            $response->addHeader('Location', '/dashboard');
            $response->setStatusCode(302);
            return $response;
        }

        return $next($request, $response);
    }
}
```

---

## Step 5: Complete Auth Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Infrastructure\Http\ApiController;
use Luminor\Auth\CurrentUser;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Session\Session;
use App\Auth\Mfa\MfaService;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Mail\PasswordResetMail;
use App\Mail\VerificationMail;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class AuthController extends ApiController
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtService $jwtService,
        private MfaService $mfaService,
        private RateLimiter $rateLimiter,
        private Session $session,
    ) {}

    // =========================================================================
    // WEB AUTHENTICATION
    // =========================================================================

    public function showLoginForm(Request $request, Response $response): Response
    {
        return $this->view($response, 'auth/login', [
            'redirect' => $request->getParam('redirect', '/dashboard'),
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';
        $remember = isset($payload['remember']);

        // Rate limiting
        $key = 'login:' . strtolower($email);
        if ($this->rateLimiter->tooManyAttempts($key, 5)) {
            return $this->view($response, 'auth/login', [
                'errors' => ['credentials' => ['Too many attempts. Please try again later.']],
                'old' => ['email' => $email],
            ]);
        }

        // Validate credentials
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->verifyPassword($password)) {
            $this->rateLimiter->hit($key);
            return $this->view($response, 'auth/login', [
                'errors' => ['credentials' => ['Invalid email or password.']],
                'old' => ['email' => $email],
            ]);
        }

        // Check MFA
        if ($this->mfaService->isEnabled($user)) {
            $this->session->set('mfa_user_id', $user->getId());
            $this->session->set('mfa_remember', $remember);

            return $this->view($response, 'auth/mfa-verify', [
                'email' => $email,
            ]);
        }

        // Complete login
        $this->rateLimiter->clear($key);
        $this->completeLogin($user, $remember);

        $redirect = $payload['redirect'] ?? '/dashboard';
        return $this->redirect($response, $redirect);
    }

    public function verifyMfa(Request $request, Response $response): Response
    {
        $userId = $this->session->get('mfa_user_id');

        if (!$userId) {
            return $this->redirect($response, '/login');
        }

        $user = $this->userRepository->find($userId);
        $code = $request->getPayload()['code'] ?? '';

        $isRecoveryCode = strlen($code) === 10;
        $valid = $isRecoveryCode
            ? $this->mfaService->verifyRecoveryCode($user, $code)
            : $this->mfaService->verify($user, $code);

        if (!$valid) {
            return $this->view($response, 'auth/mfa-verify', [
                'error' => 'Invalid code. Please try again.',
            ]);
        }

        $remember = $this->session->get('mfa_remember', false);
        $this->session->remove('mfa_user_id');
        $this->session->remove('mfa_remember');

        $this->completeLogin($user, $remember);

        return $this->redirect($response, '/dashboard');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->session->destroy();

        if ($this->isApiRequest($request)) {
            return $this->success($response, ['message' => 'Logged out']);
        }

        return $this->redirect($response, '/login');
    }

    // =========================================================================
    // API AUTHENTICATION
    // =========================================================================

    public function apiLogin(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';

        // Rate limiting
        $key = 'api_login:' . strtolower($email);
        if ($this->rateLimiter->tooManyAttempts($key, 5)) {
            return $this->error($response, 'Too many attempts', 429);
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->verifyPassword($password)) {
            $this->rateLimiter->hit($key);
            return $this->unauthorized($response, 'Invalid credentials');
        }

        // Check MFA
        if ($this->mfaService->isEnabled($user)) {
            $this->session->set('mfa_user_id', $user->getId());

            return $this->success($response, [
                'requires_mfa' => true,
                'message' => 'MFA code required',
            ]);
        }

        $this->rateLimiter->clear($key);

        return $this->generateTokenResponse($response, $user);
    }

    public function apiVerifyMfa(Request $request, Response $response): Response
    {
        $userId = $this->session->get('mfa_user_id');

        if (!$userId) {
            return $this->error($response, 'No pending MFA verification', 400);
        }

        $user = $this->userRepository->find($userId);
        $code = $request->getPayload()['code'] ?? '';

        $isRecoveryCode = strlen($code) === 10;
        $valid = $isRecoveryCode
            ? $this->mfaService->verifyRecoveryCode($user, $code)
            : $this->mfaService->verify($user, $code);

        if (!$valid) {
            return $this->error($response, 'Invalid code', 400);
        }

        $this->session->remove('mfa_user_id');

        return $this->generateTokenResponse($response, $user);
    }

    public function refresh(Request $request, Response $response): Response
    {
        $refreshToken = $request->getPayload()['refresh_token'] ?? '';

        try {
            $token = $this->jwtService->parse($refreshToken);

            if ($token->getClaim('type') !== 'refresh') {
                throw new AuthenticationException('Invalid token type');
            }

            $user = $this->userRepository->find($token->getSubject());

            if (!$user) {
                throw new AuthenticationException('User not found');
            }

            return $this->generateTokenResponse($response, $user);

        } catch (\Exception $e) {
            return $this->unauthorized($response, 'Invalid refresh token');
        }
    }

    public function me(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();

        return $this->success($response, [
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'mfa_enabled' => $this->mfaService->isEnabled($user),
            ],
        ]);
    }

    // =========================================================================
    // REGISTRATION
    // =========================================================================

    public function showRegisterForm(Request $request, Response $response): Response
    {
        return $this->view($response, 'auth/register');
    }

    public function register(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ]);

        if (!empty($errors)) {
            if ($this->isApiRequest($request)) {
                return $this->validationError($response, $errors);
            }
            return $this->view($response, 'auth/register', ['errors' => $errors, 'old' => $payload]);
        }

        // Check existing email
        if ($this->userRepository->findByEmail($payload['email'])) {
            $error = ['email' => ['This email is already registered.']];
            if ($this->isApiRequest($request)) {
                return $this->validationError($response, $error);
            }
            return $this->view($response, 'auth/register', ['errors' => $error, 'old' => $payload]);
        }

        // Create user
        $user = User::create(
            name: $payload['name'],
            email: $payload['email'],
            password: $payload['password']
        );

        $this->userRepository->save($user);

        // Send verification email
        // Mail::send(new VerificationMail($user));

        if ($this->isApiRequest($request)) {
            return $this->generateTokenResponse($response, $user, 201);
        }

        $this->completeLogin($user, false);
        $this->flash('success', 'Account created! Please verify your email.');

        return $this->redirect($response, '/dashboard');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function completeLogin(User $user, bool $remember): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $user->getId());

        $user->recordLogin();
        $this->userRepository->save($user);

        if ($remember) {
            // Set remember token cookie
        }
    }

    private function generateTokenResponse(Response $response, User $user, int $status = 200): Response
    {
        $accessToken = $this->jwtService->generate($user->getId(), [
            'email' => $user->getEmail(),
            'name' => $user->getName(),
        ]);

        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        return $this->success($response, [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $refreshToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => $accessToken->getTimeToExpiry(),
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
        ], $status);
    }

    private function redirect(Response $response, string $url): Response
    {
        $response->addHeader('Location', $url);
        $response->setStatusCode(302);
        return $response;
    }

    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/api')
            || $request->getHeader('Accept') === 'application/json';
    }
}
```

---

## Step 6: Bootstrap Application

```php
<?php

// bootstrap/app.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Luminor\Container\Container;
use Luminor\Session\Session;
use App\Providers\AuthServiceProvider;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create container
$container = new Container();

// Start session
$session = Session::start([
    'name' => 'app_session',
    'lifetime' => 7200,
    'secure' => $_ENV['APP_ENV'] === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);
$container->set(Session::class, $session);

// Register service providers
$container->register(new AuthServiceProvider());

// ... rest of bootstrap

return $container;
```

---

## Usage Examples

### API Login Flow

```bash
# 1. Login
curl -X POST https://api.myapp.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "secret123"}'

# Response (if MFA enabled):
# {"requires_mfa": true, "message": "MFA code required"}

# 2. MFA Verification
curl -X POST https://api.myapp.com/api/auth/login/mfa \
  -H "Content-Type: application/json" \
  -d '{"code": "123456"}'

# Response:
# {"access_token": "...", "refresh_token": "...", ...}

# 3. Use API
curl https://api.myapp.com/api/users \
  -H "Authorization: Bearer <access_token>"
```

### Create API Token

```bash
curl -X POST https://api.myapp.com/api/tokens \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "CI/CD Pipeline",
    "scopes": ["posts:read", "posts:write"],
    "expires_in_days": 90
  }'
```

---

## Next Steps

- [Testing Authentication](./testing-authentication.md)
- [Authorization and Policies](./authorization-rbac.md)
- [Security Best Practices](../AUTHENTICATION.md#security-best-practices)
