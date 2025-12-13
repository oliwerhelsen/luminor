<?php

declare(strict_types=1);

/**
 * Complete Authentication Setup Example
 *
 * This example demonstrates how to set up all authentication methods:
 * - JWT Bearer Tokens
 * - OpenID Connect (Azure AD)
 * - Session-based authentication
 * - API Tokens
 * - Multi-Factor Authentication
 * - Rate Limiting
 */

require_once __DIR__ . '/../vendor/autoload.php';

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
use Luminor\Auth\Mfa\TotpService;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Auth\RateLimit\ArrayRateLimitStore;
use Luminor\Auth\CurrentUser;
use Luminor\Session\Session;
use Luminor\Http\Request;
use Luminor\Http\Response;

// ============================================================================
// 1. SETUP AUTHENTICATION SERVICES
// ============================================================================

// Initialize Authentication Manager
$authManager = new AuthenticationManager();

// Initialize JWT Service
$jwtService = new JwtService(
    secret: $_ENV['JWT_SECRET'] ?? 'your-secret-key-minimum-32-characters-long',
    ttl: 3600, // 1 hour
    issuer: 'luminor-example'
);

// Initialize Session
$session = Session::start();

// Initialize Rate Limiter
$rateLimiter = new RateLimiter(
    store: new ArrayRateLimitStore(),
    maxAttempts: 5,
    decaySeconds: 60
);

// Initialize MFA Service (requires MfaRepository implementation)
$totpService = new TotpService();
// $mfaService = new MfaService($totpService, $mfaRepository);

// User resolver function (replace with your actual User model)
$userResolver = function ($identifier, $byEmail = false) {
    // This is a mock implementation - replace with your actual database query
    if ($byEmail) {
        // return User::where('email', $identifier)->first();
        return (object)[
            'id' => 1,
            'email' => $identifier,
            'name' => 'John Doe',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        ];
    }

    // return User::find($identifier);
    return (object)[
        'id' => $identifier,
        'email' => 'user@example.com',
        'name' => 'John Doe',
    ];
};

// ============================================================================
// 2. REGISTER AUTHENTICATION PROVIDERS
// ============================================================================

// JWT Provider
$jwtProvider = new JwtAuthenticationProvider($jwtService, $userResolver);
$authManager->register($jwtProvider);

// Session Provider
$sessionProvider = new SessionAuthenticationProvider($session, $userResolver);
$authManager->register($sessionProvider);

// API Token Provider (requires ApiTokenRepository implementation)
// $apiTokenProvider = new ApiTokenAuthenticationProvider($apiTokenManager, $userResolver);
// $authManager->register($apiTokenProvider);

// OpenID Connect Provider (Azure AD example)
if (isset($_ENV['AZURE_TENANT_ID'])) {
    $oidcProvider = OpenIdProvider::azure(
        tenantId: $_ENV['AZURE_TENANT_ID'],
        clientId: $_ENV['AZURE_CLIENT_ID'],
        clientSecret: $_ENV['AZURE_CLIENT_SECRET'],
        redirectUri: $_ENV['AZURE_REDIRECT_URI']
    );

    $oidcService = new OpenIdService($oidcProvider);

    $oidcAuthProvider = new OpenIdAuthenticationProvider(
        $oidcService,
        function (array $claims, array $tokens) {
            // Find or create user from OIDC claims
            return (object)[
                'id' => $claims['sub'],
                'email' => $claims['email'],
                'name' => $claims['name'] ?? '',
                'email_verified' => $claims['email_verified'] ?? false,
            ];
        }
    );

    $authManager->register($oidcAuthProvider);
}

// ============================================================================
// 3. AUTHENTICATION EXAMPLES
// ============================================================================

echo "=== Luminor Authentication Examples ===\n\n";

// ----------------------------------------------------------------------------
// Example 1: JWT Authentication
// ----------------------------------------------------------------------------
echo "1. JWT Authentication\n";
echo "---------------------\n";

// Generate JWT token for a user
$user = $userResolver(1);
$jwtToken = $jwtService->generate($user->id, [
    'email' => $user->email,
    'name' => $user->name,
]);

echo "Access Token: " . $jwtToken->getToken() . "\n";
echo "Expires in: " . $jwtToken->getTimeToExpiry() . " seconds\n";

// Generate refresh token
$refreshToken = $jwtService->generateRefreshToken($user->id);
echo "Refresh Token: " . substr($refreshToken->getToken(), 0, 40) . "...\n";

// Validate JWT token
try {
    $parsedToken = $jwtService->parse($jwtToken->getToken());
    echo "✓ Token is valid\n";
    echo "  Subject: " . $parsedToken->getSubject() . "\n";
    echo "  Email: " . $parsedToken->getClaim('email') . "\n";
} catch (Exception $e) {
    echo "✗ Token validation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// ----------------------------------------------------------------------------
// Example 2: Session Authentication
// ----------------------------------------------------------------------------
echo "2. Session Authentication\n";
echo "-------------------------\n";

// Login with credentials
try {
    $authenticatedUser = $sessionProvider->attempt([
        'email' => 'user@example.com',
        'password' => 'password123',
    ], remember: false);

    echo "✓ Login successful\n";
    echo "  User: " . $authenticatedUser->name . "\n";
    echo "  Email: " . $authenticatedUser->email . "\n";
} catch (Exception $e) {
    echo "✗ Login failed: " . $e->getMessage() . "\n";
}

echo "\n";

// ----------------------------------------------------------------------------
// Example 3: Multi-Factor Authentication (TOTP)
// ----------------------------------------------------------------------------
echo "3. Multi-Factor Authentication\n";
echo "------------------------------\n";

// Generate MFA secret
$mfaSecret = $totpService->generateSecret();
echo "MFA Secret: " . $mfaSecret . "\n";

// Generate QR code URI for authenticator apps
$qrCodeUri = $totpService->getQrCodeUri($mfaSecret, 'user@example.com', 'Luminor App');
echo "QR Code URI: " . $qrCodeUri . "\n";

// Generate current TOTP code
$totpCode = $totpService->generateCode($mfaSecret);
echo "Current TOTP Code: " . $totpCode . "\n";

// Verify TOTP code
$isValid = $totpService->verify($totpCode, $mfaSecret);
echo "✓ Code verification: " . ($isValid ? "VALID" : "INVALID") . "\n";

echo "\n";

// ----------------------------------------------------------------------------
// Example 4: Rate Limiting
// ----------------------------------------------------------------------------
echo "4. Rate Limiting\n";
echo "----------------\n";

$loginKey = 'login:192.168.1.1';

// Simulate login attempts
for ($i = 1; $i <= 7; $i++) {
    if ($rateLimiter->attempt($loginKey)) {
        echo "Attempt {$i}: ✓ Allowed (remaining: " . $rateLimiter->remaining($loginKey) . ")\n";
    } else {
        echo "Attempt {$i}: ✗ Rate limited (retry in: " . $rateLimiter->availableIn($loginKey) . "s)\n";
    }
}

// Clear rate limit
$rateLimiter->clear($loginKey);
echo "Rate limit cleared\n";

echo "\n";

// ----------------------------------------------------------------------------
// Example 5: OpenID Connect (Azure AD)
// ----------------------------------------------------------------------------
if (isset($oidcService)) {
    echo "5. OpenID Connect (Azure AD)\n";
    echo "----------------------------\n";

    // Generate authorization URL
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $authUrl = $oidcService->getAuthorizationUrl($state, $nonce);

    echo "Authorization URL:\n";
    echo substr($authUrl, 0, 100) . "...\n";
    echo "\nUser would be redirected to this URL to authenticate\n";
    echo "\n";
}

// ----------------------------------------------------------------------------
// Example 6: API Token Generation
// ----------------------------------------------------------------------------
echo "6. API Token Management\n";
echo "-----------------------\n";

// Manually generate an API token (normally done via ApiTokenManager)
$apiTokenValue = bin2hex(random_bytes(40));
$hashedToken = hash('sha256', $apiTokenValue);

echo "API Token: " . $apiTokenValue . "\n";
echo "Hashed (for storage): " . $hashedToken . "\n";
echo "Scopes: users:read, posts:write\n";
echo "Expires: 1 year from now\n";

echo "\n";

// ----------------------------------------------------------------------------
// Example 7: Using CurrentUser
// ----------------------------------------------------------------------------
echo "7. Current User Context\n";
echo "-----------------------\n";

// Set current user
$currentUser = $userResolver(1);
CurrentUser::set($currentUser);

echo "Current user: " . CurrentUser::get()->name . "\n";
echo "Is authenticated: " . (CurrentUser::isAuthenticated() ? 'Yes' : 'No') . "\n";

// Act as different user
CurrentUser::actingAs($userResolver(2), function () {
    echo "Acting as: " . CurrentUser::get()->name . "\n";
});

echo "Back to: " . CurrentUser::get()->name . "\n";

// Clear current user
CurrentUser::clear();
echo "User cleared. Is guest: " . (CurrentUser::isGuest() ? 'Yes' : 'No') . "\n";

echo "\n";

// ============================================================================
// 8. AUTHENTICATION FLOW EXAMPLE
// ============================================================================
echo "8. Complete Authentication Flow\n";
echo "================================\n";

// Simulate a request with JWT token
class MockRequest extends Request
{
    private array $headers = [];

    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function query(string $key): mixed
    {
        return null;
    }

    public function getPath(): string
    {
        return '/api/user';
    }
}

// Create a request with Bearer token
$mockRequest = new MockRequest([
    'Authorization' => 'Bearer ' . $jwtToken->getToken(),
]);

// Authenticate using AuthenticationManager
try {
    $authenticatedUser = $authManager->authenticate($mockRequest);

    if ($authenticatedUser) {
        echo "✓ User authenticated via JWT\n";
        echo "  User ID: " . ($authenticatedUser->id ?? 'N/A') . "\n";
        echo "  Email: " . ($authenticatedUser->email ?? 'N/A') . "\n";
    } else {
        echo "✗ Authentication failed\n";
    }
} catch (Exception $e) {
    echo "✗ Authentication error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "=== Examples Complete ===\n";
