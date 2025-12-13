---
title: Testing Authentication Guide
layout: default
parent: Authentication
nav_order: 7
description: "Learn how to write comprehensive tests for your authentication system"
---

# Testing Authentication Guide

This guide covers how to write comprehensive tests for your authentication system, including unit tests, integration tests, and end-to-end tests.

## What You'll Learn

- Testing authentication providers
- Testing JWT token generation and validation
- Testing MFA flows
- Testing API token authentication
- Mocking authentication in other tests
- Security testing best practices

## Prerequisites

- PHPUnit installed
- Luminor testing utilities
- Basic understanding of testing concepts

---

## Setting Up Test Environment

### Test Configuration

Create a test environment file:

```bash
# .env.testing
APP_ENV=testing

JWT_SECRET="test-secret-key-minimum-32-characters-long"
JWT_TTL=3600
JWT_ISSUER="test-app"

RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_DECAY_SECONDS=1
```

### Base Test Case

```php
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Luminor\Container\Container;
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\CurrentUser;
use Luminor\Session\Session;
use App\Domain\User\User;

abstract class AuthTestCase extends TestCase
{
    protected Container $container;
    protected AuthenticationManager $authManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Load test environment
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['JWT_SECRET'] = 'test-secret-key-minimum-32-characters-long';

        // Create fresh container
        $this->container = new Container();

        // Bootstrap test dependencies
        $this->bootstrapTestDependencies();

        // Clear current user
        CurrentUser::clear();
    }

    protected function tearDown(): void
    {
        CurrentUser::clear();
        parent::tearDown();
    }

    protected function bootstrapTestDependencies(): void
    {
        // Override with test implementations
        // See specific test classes for examples
    }

    /**
     * Create a test user
     */
    protected function createUser(array $attributes = []): User
    {
        return User::create(
            name: $attributes['name'] ?? 'Test User',
            email: $attributes['email'] ?? 'test@example.com',
            password: $attributes['password'] ?? 'password123'
        );
    }

    /**
     * Act as a specific user
     */
    protected function actingAs(User $user): self
    {
        CurrentUser::set($user);
        return $this;
    }

    /**
     * Assert user is authenticated
     */
    protected function assertAuthenticated(): void
    {
        $this->assertTrue(CurrentUser::isAuthenticated(), 'User should be authenticated');
    }

    /**
     * Assert user is guest
     */
    protected function assertGuest(): void
    {
        $this->assertTrue(CurrentUser::isGuest(), 'User should be a guest');
    }
}
```

---

## Testing JWT Authentication

### JWT Service Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\AuthTestCase;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\AuthenticationException;

final class JwtServiceTest extends AuthTestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService(
            secret: 'test-secret-key-minimum-32-characters-long',
            ttl: 3600,
            issuer: 'test-app'
        );
    }

    public function testGeneratesValidToken(): void
    {
        $userId = 'user-123';

        $token = $this->jwtService->generate($userId, [
            'email' => 'test@example.com',
        ]);

        $this->assertNotEmpty($token->getToken());
        $this->assertEquals(2, substr_count($token->getToken(), '.'));
        $this->assertGreaterThan(0, $token->getTimeToExpiry());
    }

    public function testParsesValidToken(): void
    {
        $userId = 'user-123';
        $email = 'test@example.com';

        $token = $this->jwtService->generate($userId, ['email' => $email]);
        $parsed = $this->jwtService->parse($token->getToken());

        $this->assertEquals($userId, $parsed->getSubject());
        $this->assertEquals($email, $parsed->getClaim('email'));
        $this->assertEquals('test-app', $parsed->getClaim('iss'));
    }

    public function testRejectsInvalidSignature(): void
    {
        $token = $this->jwtService->generate('user-123');

        // Tamper with the token
        $parts = explode('.', $token->getToken());
        $parts[2] = 'invalid-signature';
        $tamperedToken = implode('.', $parts);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid signature');

        $this->jwtService->parse($tamperedToken);
    }

    public function testRejectsExpiredToken(): void
    {
        // Create service with 0 TTL
        $service = new JwtService(
            secret: 'test-secret-key-minimum-32-characters-long',
            ttl: -1, // Already expired
            issuer: 'test-app'
        );

        $token = $service->generate('user-123');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token has expired');

        $service->parse($token->getToken());
    }

    public function testGeneratesRefreshToken(): void
    {
        $userId = 'user-123';

        $token = $this->jwtService->generateRefreshToken($userId);

        $this->assertNotEmpty($token->getToken());

        $parsed = $this->jwtService->parse($token->getToken());
        $this->assertEquals('refresh', $parsed->getClaim('type'));
        $this->assertEquals($userId, $parsed->getSubject());
    }

    public function testValidatesToken(): void
    {
        $token = $this->jwtService->generate('user-123');

        $this->assertTrue($this->jwtService->validate($token->getToken()));
        $this->assertFalse($this->jwtService->validate('invalid-token'));
    }
}
```

### JWT Authentication Provider Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\AuthTestCase;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Jwt\JwtAuthenticationProvider;
use Luminor\Auth\AuthenticationException;
use App\Domain\User\User;

final class JwtAuthenticationProviderTest extends AuthTestCase
{
    private JwtService $jwtService;
    private JwtAuthenticationProvider $provider;
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService(
            secret: 'test-secret-key-minimum-32-characters-long',
            ttl: 3600,
            issuer: 'test-app'
        );

        // Create test users
        $this->users['user-123'] = $this->createUser([
            'email' => 'test@example.com'
        ]);

        // User resolver
        $userResolver = fn($id) => $this->users[$id] ?? null;

        $this->provider = new JwtAuthenticationProvider($this->jwtService, $userResolver);
    }

    public function testSupportsRequestWithBearerToken(): void
    {
        $request = $this->createMockRequest([
            'Authorization' => 'Bearer some-token',
        ]);

        $this->assertTrue($this->provider->supports($request));
    }

    public function testDoesNotSupportRequestWithoutToken(): void
    {
        $request = $this->createMockRequest([]);

        $this->assertFalse($this->provider->supports($request));
    }

    public function testAuthenticatesValidToken(): void
    {
        $user = $this->users['user-123'];
        $token = $this->jwtService->generate('user-123');

        $request = $this->createMockRequest([
            'Authorization' => 'Bearer ' . $token->getToken(),
        ]);

        $result = $this->provider->authenticate($request);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->getEmail(), $result->getEmail());
    }

    public function testReturnsNullForInvalidToken(): void
    {
        $request = $this->createMockRequest([
            'Authorization' => 'Bearer invalid-token',
        ]);

        $this->expectException(AuthenticationException::class);

        $this->provider->authenticate($request);
    }

    public function testReturnsNullForUnknownUser(): void
    {
        $token = $this->jwtService->generate('unknown-user');

        $request = $this->createMockRequest([
            'Authorization' => 'Bearer ' . $token->getToken(),
        ]);

        $result = $this->provider->authenticate($request);

        $this->assertNull($result);
    }

    private function createMockRequest(array $headers): object
    {
        return new class($headers) {
            public function __construct(private array $headers) {}

            public function getHeader(string $name): ?string
            {
                return $this->headers[$name] ?? null;
            }
        };
    }
}
```

---

## Testing MFA

### TOTP Service Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Mfa;

use Tests\AuthTestCase;
use App\Auth\Mfa\TotpService;

final class TotpServiceTest extends AuthTestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
    }

    public function testGeneratesSecret(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertEquals(32, strlen($secret)); // Base32 encoded 20 bytes
    }

    public function testGeneratesValidCode(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->generateCode($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testVerifiesValidCode(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->generateCode($secret);

        $this->assertTrue($this->totpService->verify($code, $secret));
    }

    public function testRejectsInvalidCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertFalse($this->totpService->verify('000000', $secret));
        $this->assertFalse($this->totpService->verify('123456', $secret));
    }

    public function testGeneratesQrCodeUri(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $email = 'test@example.com';
        $issuer = 'MyApp';

        $uri = $this->totpService->getQrCodeUri($secret, $email, $issuer);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString($secret, $uri);
        $this->assertStringContainsString(urlencode($email), $uri);
        $this->assertStringContainsString(urlencode($issuer), $uri);
    }

    public function testAllowsTimeDriftWithinWindow(): void
    {
        $secret = $this->totpService->generateSecret();

        // This test is timing-sensitive; the code should be valid
        // within the time window (±30 seconds by default)
        $code = $this->totpService->generateCode($secret);

        // Small delay should still verify
        usleep(100000); // 0.1 seconds

        $this->assertTrue($this->totpService->verify($code, $secret));
    }
}
```

### MFA Service Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Mfa;

use Tests\AuthTestCase;
use App\Auth\Mfa\MfaService;
use App\Auth\Mfa\TotpService;
use App\Domain\User\User;
use App\Domain\User\InMemoryUserRepository;
use Luminor\Auth\AuthenticationException;

final class MfaServiceTest extends AuthTestCase
{
    private MfaService $mfaService;
    private TotpService $totpService;
    private InMemoryUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totpService = new TotpService();
        $this->userRepository = new InMemoryUserRepository();

        $this->mfaService = new MfaService(
            $this->totpService,
            $this->userRepository
        );
    }

    public function testInitializesMfaSetup(): void
    {
        $user = $this->createUser();
        $this->userRepository->save($user);

        $data = $this->mfaService->initialize($user);

        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qr_code_uri', $data);
        $this->assertNotEmpty($data['secret']);
    }

    public function testConfirmsAndEnablesMfa(): void
    {
        $user = $this->createUser();
        $this->userRepository->save($user);

        // Initialize
        $data = $this->mfaService->initialize($user);
        $secret = $data['secret'];

        // Generate valid code
        $code = $this->totpService->generateCode($secret);

        // Confirm
        $result = $this->mfaService->confirm($user, $code);

        $this->assertArrayHasKey('recovery_codes', $result);
        $this->assertCount(8, $result['recovery_codes']);
        $this->assertTrue($this->mfaService->isEnabled($user));
    }

    public function testRejectsInvalidConfirmationCode(): void
    {
        $user = $this->createUser();
        $this->userRepository->save($user);

        $this->mfaService->initialize($user);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid verification code');

        $this->mfaService->confirm($user, '000000');
    }

    public function testVerifiesValidCode(): void
    {
        $user = $this->setupMfaEnabledUser();

        $code = $this->totpService->generateCode($user->getMfaSecret());

        $this->assertTrue($this->mfaService->verify($user, $code));
    }

    public function testRejectsInvalidCode(): void
    {
        $user = $this->setupMfaEnabledUser();

        $this->assertFalse($this->mfaService->verify($user, '000000'));
    }

    public function testVerifiesRecoveryCode(): void
    {
        $user = $this->setupMfaEnabledUser();

        $recoveryCodes = $user->getMfaRecoveryCodes();
        $code = $recoveryCodes[0]['code'];

        $this->assertTrue($this->mfaService->verifyRecoveryCode($user, $code));

        // Code should be marked as used
        $updatedUser = $this->userRepository->find($user->getId());
        $this->assertTrue($updatedUser->getMfaRecoveryCodes()[0]['used']);
    }

    public function testRecoveryCodeCanOnlyBeUsedOnce(): void
    {
        $user = $this->setupMfaEnabledUser();

        $recoveryCodes = $user->getMfaRecoveryCodes();
        $code = $recoveryCodes[0]['code'];

        // First use should succeed
        $this->assertTrue($this->mfaService->verifyRecoveryCode($user, $code));

        // Second use should fail
        $updatedUser = $this->userRepository->find($user->getId());
        $this->assertFalse($this->mfaService->verifyRecoveryCode($updatedUser, $code));
    }

    public function testDisablesMfa(): void
    {
        $user = $this->setupMfaEnabledUser();

        $this->mfaService->disable($user, 'password123');

        $this->assertFalse($this->mfaService->isEnabled($user));
    }

    public function testDisableRequiresCorrectPassword(): void
    {
        $user = $this->setupMfaEnabledUser();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid password');

        $this->mfaService->disable($user, 'wrong-password');
    }

    private function setupMfaEnabledUser(): User
    {
        $user = $this->createUser(['password' => 'password123']);
        $this->userRepository->save($user);

        $data = $this->mfaService->initialize($user);
        $code = $this->totpService->generateCode($data['secret']);
        $this->mfaService->confirm($user, $code);

        return $this->userRepository->find($user->getId());
    }
}
```

---

## Testing API Tokens

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\AuthTestCase;
use App\Auth\ApiTokenService;
use App\Auth\TokenScopes;
use App\Domain\ApiToken\InMemoryApiTokenRepository;
use Luminor\Auth\AuthenticationException;

final class ApiTokenServiceTest extends AuthTestCase
{
    private ApiTokenService $tokenService;
    private InMemoryApiTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryApiTokenRepository();
        $this->tokenService = new ApiTokenService($this->repository);
    }

    public function testCreatesToken(): void
    {
        $userId = 'user-123';
        $name = 'Test Token';
        $scopes = ['users:read', 'posts:write'];

        $token = $this->tokenService->create($userId, $name, $scopes);

        $this->assertNotEmpty($token->getId());
        $this->assertNotEmpty($token->getPlainToken());
        $this->assertEquals($name, $token->getName());
        $this->assertEquals($scopes, $token->getScopes());
        $this->assertEquals($userId, $token->getUserId());
    }

    public function testValidatesToken(): void
    {
        $token = $this->tokenService->create('user-123', 'Test', ['users:read']);
        $plainToken = $token->getPlainToken();

        $validated = $this->tokenService->validate($plainToken);

        $this->assertEquals($token->getId(), $validated->getId());
    }

    public function testRejectsInvalidToken(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API token');

        $this->tokenService->validate('invalid-token');
    }

    public function testRejectsExpiredToken(): void
    {
        // Create token that expires immediately
        $token = $this->tokenService->create(
            userId: 'user-123',
            name: 'Test',
            scopes: [],
            expiresInDays: -1 // Already expired
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API token has expired');

        $this->tokenService->validate($token->getPlainToken());
    }

    public function testRevokesToken(): void
    {
        $token = $this->tokenService->create('user-123', 'Test', []);
        $plainToken = $token->getPlainToken();

        $this->tokenService->revoke($token->getId(), 'user-123');

        $this->expectException(AuthenticationException::class);

        $this->tokenService->validate($plainToken);
    }

    public function testCannotRevokeOtherUsersToken(): void
    {
        $token = $this->tokenService->create('user-123', 'Test', []);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token not found');

        $this->tokenService->revoke($token->getId(), 'other-user');
    }

    public function testChecksTokenScopes(): void
    {
        $token = $this->tokenService->create('user-123', 'Test', ['users:read', 'posts:write']);

        $this->assertTrue($token->hasScope('users:read'));
        $this->assertTrue($token->hasScope('posts:write'));
        $this->assertFalse($token->hasScope('users:delete'));
    }

    public function testAdminScopeGrantsAllAccess(): void
    {
        $token = $this->tokenService->create('user-123', 'Admin', ['admin']);

        $this->assertTrue($token->hasScope('users:read'));
        $this->assertTrue($token->hasScope('users:write'));
        $this->assertTrue($token->hasScope('users:delete'));
        $this->assertTrue($token->hasScope('anything:else'));
    }

    public function testRecordsTokenUsage(): void
    {
        $token = $this->tokenService->create('user-123', 'Test', []);
        $ip = '192.168.1.1';

        $this->tokenService->recordUsage($token, $ip);

        $updated = $this->repository->find($token->getId());

        $this->assertEquals($ip, $updated->getLastUsedIp());
        $this->assertNotNull($updated->getLastUsedAt());
    }

    public function testCleansUpExpiredTokens(): void
    {
        // Create expired token
        $this->tokenService->create('user-123', 'Expired', [], -1);

        // Create valid token
        $validToken = $this->tokenService->create('user-123', 'Valid', [], 30);

        $deleted = $this->tokenService->cleanupExpired();

        $this->assertEquals(1, $deleted);
        $this->assertNotNull($this->repository->find($validToken->getId()));
    }
}
```

---

## Testing Rate Limiting

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\AuthTestCase;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Auth\RateLimit\ArrayRateLimitStore;

final class RateLimiterTest extends AuthTestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = new RateLimiter(
            store: new ArrayRateLimitStore(),
            maxAttempts: 5,
            decaySeconds: 60
        );
    }

    public function testAllowsAttemptsWithinLimit(): void
    {
        $key = 'test:user@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->rateLimiter->attempt($key));
        }
    }

    public function testBlocksExcessiveAttempts(): void
    {
        $key = 'test:user@example.com';

        // Use up all attempts
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->hit($key);
        }

        $this->assertTrue($this->rateLimiter->tooManyAttempts($key, 5));
        $this->assertFalse($this->rateLimiter->attempt($key));
    }

    public function testTracksRemainingAttempts(): void
    {
        $key = 'test:user@example.com';

        $this->assertEquals(5, $this->rateLimiter->remaining($key));

        $this->rateLimiter->hit($key);
        $this->assertEquals(4, $this->rateLimiter->remaining($key));

        $this->rateLimiter->hit($key);
        $this->rateLimiter->hit($key);
        $this->assertEquals(2, $this->rateLimiter->remaining($key));
    }

    public function testClearsAttempts(): void
    {
        $key = 'test:user@example.com';

        // Use up attempts
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->hit($key);
        }

        $this->assertTrue($this->rateLimiter->tooManyAttempts($key, 5));

        // Clear
        $this->rateLimiter->clear($key);

        $this->assertFalse($this->rateLimiter->tooManyAttempts($key, 5));
        $this->assertEquals(5, $this->rateLimiter->remaining($key));
    }

    public function testReturnsRetryAfterTime(): void
    {
        $key = 'test:user@example.com';

        // Use up attempts
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->hit($key);
        }

        $retryAfter = $this->rateLimiter->availableIn($key);

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }
}
```

---

## Integration Tests

### Login Flow Test

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\IntegrationTestCase;
use App\Domain\User\User;

final class LoginFlowTest extends IntegrationTestCase
{
    public function testSuccessfulLoginWithoutMfa(): void
    {
        $user = $this->createUser([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        $this->userRepository->save($user);

        $response = $this->post('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertEquals('Bearer', $data['token_type']);
    }

    public function testLoginRequiresMfaWhenEnabled(): void
    {
        $user = $this->createUserWithMfa([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['requires_mfa']);
        $this->assertArrayNotHasKey('access_token', $data);
    }

    public function testMfaVerificationCompletesLogin(): void
    {
        $user = $this->createUserWithMfa([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        // First login
        $this->post('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        // Get valid TOTP code
        $code = $this->totpService->generateCode($user->getMfaSecret());

        // Verify MFA
        $response = $this->post('/api/auth/login/mfa', [
            'code' => $code,
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('access_token', $data);
    }

    public function testInvalidCredentialsReturnsError(): void
    {
        $response = $this->post('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRateLimitingBlocksExcessiveAttempts(): void
    {
        // Make 5 failed attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->assertEquals(429, $response->getStatusCode());
    }

    public function testTokenRefresh(): void
    {
        $user = $this->createUser();
        $this->userRepository->save($user);

        // Login
        $loginResponse = $this->post('/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'password123',
        ]);

        $data = json_decode($loginResponse->getBody(), true);
        $refreshToken = $data['refresh_token'];

        // Refresh
        $response = $this->post('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $newData = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('access_token', $newData);
        $this->assertNotEquals($data['access_token'], $newData['access_token']);
    }
}
```

---

## Mocking Authentication in Other Tests

### Authentication Helper Trait

```php
<?php

declare(strict_types=1);

namespace Tests\Traits;

use Luminor\Auth\CurrentUser;
use Luminor\Auth\Jwt\JwtService;
use App\Domain\User\User;

trait AuthenticatesUsers
{
    protected ?string $authToken = null;

    /**
     * Act as an authenticated user
     */
    protected function actingAs(User $user): self
    {
        CurrentUser::set($user);

        // Generate auth token for API requests
        $jwtService = $this->container->get(JwtService::class);
        $token = $jwtService->generate($user->getId(), [
            'email' => $user->getEmail(),
        ]);

        $this->authToken = $token->getToken();

        return $this;
    }

    /**
     * Act as guest
     */
    protected function actingAsGuest(): self
    {
        CurrentUser::clear();
        $this->authToken = null;

        return $this;
    }

    /**
     * Make authenticated API request
     */
    protected function authenticatedGet(string $uri): object
    {
        return $this->get($uri, [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);
    }

    protected function authenticatedPost(string $uri, array $data = []): object
    {
        return $this->post($uri, $data, [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);
    }

    /**
     * Act as user with specific API token scopes
     */
    protected function actingWithScopes(User $user, array $scopes): self
    {
        $tokenService = $this->container->get(ApiTokenService::class);
        $token = $tokenService->create($user->getId(), 'Test Token', $scopes);

        $this->authToken = $token->getPlainToken();
        CurrentUser::set($user);

        return $this;
    }
}
```

### Using in Tests

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\FeatureTestCase;
use Tests\Traits\AuthenticatesUsers;

final class UserControllerTest extends FeatureTestCase
{
    use AuthenticatesUsers;

    public function testListUsersRequiresAuthentication(): void
    {
        $response = $this->get('/api/users');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testListUsersRequiresScope(): void
    {
        $user = $this->createUser();

        // Authenticate without the required scope
        $this->actingWithScopes($user, ['posts:read']);

        $response = $this->authenticatedGet('/api/users');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListUsersWithCorrectScope(): void
    {
        $user = $this->createUser();

        $this->actingWithScopes($user, ['users:read']);

        $response = $this->authenticatedGet('/api/users');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdminCanAccessEverything(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $this->actingWithScopes($admin, ['admin']);

        $this->assertEquals(200, $this->authenticatedGet('/api/users')->getStatusCode());
        $this->assertEquals(200, $this->authenticatedGet('/api/posts')->getStatusCode());
        $this->assertEquals(200, $this->authenticatedGet('/api/settings')->getStatusCode());
    }
}
```

---

## Security Testing

### Password Hashing Test

```php
<?php

public function testPasswordsAreProperlyHashed(): void
{
    $password = 'my-secure-password';

    $user = User::create('Test', 'test@example.com', $password);

    // Password should not be stored in plain text
    $this->assertNotEquals($password, $user->getPasswordHash());

    // Should verify correctly
    $this->assertTrue($user->verifyPassword($password));
    $this->assertFalse($user->verifyPassword('wrong-password'));

    // Hash should be bcrypt
    $this->assertTrue(password_needs_rehash($user->getPasswordHash(), PASSWORD_BCRYPT, ['cost' => 1]));
}

public function testTimingAttackResistance(): void
{
    $user = $this->createUser(['password' => 'password123']);

    // Both valid and invalid passwords should take similar time
    // This is a basic timing test - real security testing would be more thorough

    $start = microtime(true);
    $user->verifyPassword('password123');
    $validTime = microtime(true) - $start;

    $start = microtime(true);
    $user->verifyPassword('wrong-password');
    $invalidTime = microtime(true) - $start;

    // Times should be within 50% of each other (password_verify is constant-time)
    $this->assertLessThan($validTime * 1.5, $invalidTime);
    $this->assertGreaterThan($validTime * 0.5, $invalidTime);
}
```

---

## Running Tests

```bash
# Run all auth tests
php vendor/bin/phpunit tests/Unit/Auth

# Run with coverage
php vendor/bin/phpunit tests/Unit/Auth --coverage-html coverage

# Run specific test
php vendor/bin/phpunit --filter JwtServiceTest

# Run integration tests
php vendor/bin/phpunit tests/Integration/Auth
```

---

## Next Steps

- [Complete Authentication Example](./complete-example.md)
- [Security Best Practices](../AUTHENTICATION.md#security-best-practices)
- [Authorization and Policies](./authorization-rbac.md)
