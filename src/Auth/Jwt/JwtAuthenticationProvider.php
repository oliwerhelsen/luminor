<?php

declare(strict_types=1);

namespace Luminor\Auth\Jwt;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\AuthenticationProvider;
use Luminor\Http\Request;

/**
 * JWT Bearer Token Authentication Provider
 */
class JwtAuthenticationProvider implements AuthenticationProvider
{
    private JwtService $jwtService;
    private $userResolver;

    /**
     * @param JwtService $jwtService
     * @param callable $userResolver Function that takes user ID and returns AuthenticatableInterface
     */
    public function __construct(JwtService $jwtService, callable $userResolver)
    {
        $this->jwtService = $jwtService;
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

        try {
            $jwtToken = $this->jwtService->parse($token);

            // Check if it's a refresh token (should not be used for authentication)
            if ($jwtToken->getClaim('type') === 'refresh') {
                throw AuthenticationException::invalidToken('Refresh token cannot be used for authentication');
            }

            $userId = $jwtToken->getSubject();

            if (!$userId) {
                throw AuthenticationException::invalidToken('Token missing user identifier');
            }

            $user = ($this->userResolver)($userId);

            if (!$user) {
                throw AuthenticationException::userNotFound();
            }

            return $user;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw AuthenticationException::invalidToken($e->getMessage());
        }
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
        return 'jwt';
    }

    /**
     * Extract JWT token from request
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

        // Check X-Auth-Token header
        $tokenHeader = $request->header('X-Auth-Token');
        if ($tokenHeader) {
            return $tokenHeader;
        }

        // Check query parameter (not recommended, but supported)
        return $request->query('token');
    }
}
