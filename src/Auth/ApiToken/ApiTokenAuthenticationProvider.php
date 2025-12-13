<?php

declare(strict_types=1);

namespace Luminor\Auth\ApiToken;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\AuthenticationProvider;
use Luminor\Http\Request;

/**
 * API Token Authentication Provider
 */
class ApiTokenAuthenticationProvider implements AuthenticationProvider
{
    private ApiTokenManager $tokenManager;
    private $userResolver;

    /**
     * @param ApiTokenManager $tokenManager
     * @param callable $userResolver Function that takes user ID and returns AuthenticatableInterface
     */
    public function __construct(ApiTokenManager $tokenManager, callable $userResolver)
    {
        $this->tokenManager = $tokenManager;
        $this->userResolver = $userResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request): ?AuthenticatableInterface
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return null;
        }

        $apiToken = $this->tokenManager->validate($token);

        if (!$apiToken) {
            throw AuthenticationException::invalidToken('Invalid or expired API token');
        }

        $user = ($this->userResolver)($apiToken->getUserId());

        if (!$user) {
            throw AuthenticationException::userNotFound();
        }

        // Store token scopes in request for authorization checks
        $request->attributes['api_token_scopes'] = $apiToken->getScopes();

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        return $this->extractToken($request) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'api_token';
    }

    /**
     * Extract API token from request
     *
     * @param Request $request
     * @return string|null
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization header: "Bearer <token>"
        $authHeader = $request->header('Authorization');

        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check X-API-Token header
        $tokenHeader = $request->header('X-API-Token');
        if ($tokenHeader) {
            return $tokenHeader;
        }

        // Check api_token query parameter (not recommended for production)
        return $request->query('api_token');
    }
}
