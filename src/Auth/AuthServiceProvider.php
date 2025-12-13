<?php

declare(strict_types=1);

namespace Luminor\Auth;

use Luminor\Auth\ApiToken\ApiTokenManager;
use Luminor\Auth\Jwt\JwtService;
use Luminor\Auth\Mfa\MfaService;
use Luminor\Auth\Mfa\TotpService;
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Auth\RateLimit\ArrayRateLimitStore;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Container\Container;
use Luminor\Container\ServiceProvider;

/**
 * Authentication Service Provider
 * Registers authentication services in the container
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Register Authentication Manager
        $container->singleton(AuthenticationManager::class, function () {
            return new AuthenticationManager();
        });

        // Register JWT Service
        $container->singleton(JwtService::class, function () {
            $secret = $_ENV['JWT_SECRET'] ?? 'change-this-to-a-secure-secret-key-at-least-32-chars';
            $ttl = (int)($_ENV['JWT_TTL'] ?? 3600);
            $issuer = $_ENV['JWT_ISSUER'] ?? 'luminor';

            return new JwtService($secret, $ttl, $issuer);
        });

        // Register TOTP Service for MFA
        $container->singleton(TotpService::class, function () {
            return new TotpService();
        });

        // Register Rate Limiter
        $container->singleton(RateLimiter::class, function () {
            $store = new ArrayRateLimitStore();
            $maxAttempts = (int)($_ENV['RATE_LIMIT_MAX_ATTEMPTS'] ?? 5);
            $decaySeconds = (int)($_ENV['RATE_LIMIT_DECAY_SECONDS'] ?? 60);

            return new RateLimiter($store, $maxAttempts, $decaySeconds);
        });

        // Aliases for easier access
        $container->alias('auth', AuthenticationManager::class);
        $container->alias('jwt', JwtService::class);
        $container->alias('mfa', MfaService::class);
        $container->alias('rate_limiter', RateLimiter::class);
    }

    /**
     * Create OpenID Connect provider from environment
     *
     * @param string $provider Provider name (azure, google, okta, custom)
     * @return OpenIdProvider|null
     */
    public static function createOidcProvider(string $provider): ?OpenIdProvider
    {
        $prefix = strtoupper($provider);

        switch ($provider) {
            case 'azure':
                $tenantId = $_ENV["{$prefix}_TENANT_ID"] ?? null;
                $clientId = $_ENV["{$prefix}_CLIENT_ID"] ?? null;
                $clientSecret = $_ENV["{$prefix}_CLIENT_SECRET"] ?? null;
                $redirectUri = $_ENV["{$prefix}_REDIRECT_URI"] ?? null;

                if ($tenantId && $clientId && $clientSecret && $redirectUri) {
                    return OpenIdProvider::azure($tenantId, $clientId, $clientSecret, $redirectUri);
                }
                break;

            case 'google':
                $clientId = $_ENV["{$prefix}_CLIENT_ID"] ?? null;
                $clientSecret = $_ENV["{$prefix}_CLIENT_SECRET"] ?? null;
                $redirectUri = $_ENV["{$prefix}_REDIRECT_URI"] ?? null;

                if ($clientId && $clientSecret && $redirectUri) {
                    return OpenIdProvider::google($clientId, $clientSecret, $redirectUri);
                }
                break;

            case 'okta':
                $domain = $_ENV["{$prefix}_DOMAIN"] ?? null;
                $clientId = $_ENV["{$prefix}_CLIENT_ID"] ?? null;
                $clientSecret = $_ENV["{$prefix}_CLIENT_SECRET"] ?? null;
                $redirectUri = $_ENV["{$prefix}_REDIRECT_URI"] ?? null;

                if ($domain && $clientId && $clientSecret && $redirectUri) {
                    return OpenIdProvider::okta($domain, $clientId, $clientSecret, $redirectUri);
                }
                break;

            case 'custom':
                $name = $_ENV["{$prefix}_NAME"] ?? 'custom';
                $clientId = $_ENV["{$prefix}_CLIENT_ID"] ?? null;
                $clientSecret = $_ENV["{$prefix}_CLIENT_SECRET"] ?? null;
                $discoveryUrl = $_ENV["{$prefix}_DISCOVERY_URL"] ?? null;
                $redirectUri = $_ENV["{$prefix}_REDIRECT_URI"] ?? null;

                if ($clientId && $clientSecret && $discoveryUrl && $redirectUri) {
                    return new OpenIdProvider($name, $clientId, $clientSecret, $discoveryUrl, $redirectUri);
                }
                break;
        }

        return null;
    }
}
