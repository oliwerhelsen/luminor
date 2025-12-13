<?php

declare(strict_types=1);

namespace Luminor\Auth\OpenId;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\AuthenticationProvider;
use Luminor\Http\Request;

/**
 * OpenID Connect Authentication Provider
 */
class OpenIdAuthenticationProvider implements AuthenticationProvider
{
    private OpenIdService $oidcService;
    private $userResolver;

    /**
     * @param OpenIdService $oidcService
     * @param callable $userResolver Function that takes OIDC claims and returns/creates AuthenticatableInterface
     */
    public function __construct(OpenIdService $oidcService, callable $userResolver)
    {
        $this->oidcService = $oidcService;
        $this->userResolver = $userResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request): ?AuthenticatableInterface
    {
        // This provider handles the callback from OIDC provider
        // The actual login flow is:
        // 1. Redirect user to OIDC provider (handled by controller)
        // 2. User authenticates at OIDC provider
        // 3. OIDC provider redirects back with code
        // 4. This provider exchanges code for tokens and gets user info

        $code = $request->query('code');

        if (!$code) {
            return null;
        }

        try {
            // Exchange authorization code for tokens
            $tokens = $this->oidcService->exchangeCode($code);

            // Get user info
            $userInfo = $this->oidcService->getUserInfo($tokens['access_token']);

            // Parse ID token for additional claims
            if (isset($tokens['id_token'])) {
                $idTokenClaims = $this->oidcService->parseIdToken($tokens['id_token']);
                $userInfo = array_merge($userInfo, $idTokenClaims);
            }

            // Resolve user from claims (create if doesn't exist)
            $user = ($this->userResolver)($userInfo, $tokens);

            if (!$user) {
                throw AuthenticationException::userNotFound();
            }

            return $user;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw AuthenticationException::invalidToken("OIDC authentication failed: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        // This provider supports requests that have an authorization code
        return $request->query('code') !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'openid';
    }

    public function getService(): OpenIdService
    {
        return $this->oidcService;
    }
}
