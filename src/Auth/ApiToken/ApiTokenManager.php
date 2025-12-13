<?php

declare(strict_types=1);

namespace Luminor\Auth\ApiToken;

use Luminor\Auth\AuthenticatableInterface;

/**
 * API Token Manager
 * Manages creation, validation, and revocation of API tokens
 */
class ApiTokenManager
{
    private ApiTokenRepository $repository;

    public function __construct(ApiTokenRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new API token for a user
     *
     * @param AuthenticatableInterface $user
     * @param string $name Token name/description
     * @param array $scopes Permissions/scopes for this token
     * @param int|null $expiresInSeconds Time to expiration (null = no expiration)
     * @return ApiToken
     */
    public function create(
        AuthenticatableInterface $user,
        string $name,
        array $scopes = ['*'],
        ?int $expiresInSeconds = null
    ): ApiToken {
        $token = ApiToken::generate();
        $expiresAt = $expiresInSeconds ? time() + $expiresInSeconds : null;

        $apiToken = new ApiToken(
            $user->getAuthIdentifier(),
            $name,
            $token,
            $scopes,
            $expiresAt
        );

        $this->repository->save($apiToken);

        return $apiToken;
    }

    /**
     * Validate an API token
     *
     * @param string $token
     * @return ApiToken|null
     */
    public function validate(string $token): ?ApiToken
    {
        $hashedToken = ApiToken::hash($token);
        $apiToken = $this->repository->findByHashedToken($hashedToken);

        if (!$apiToken) {
            return null;
        }

        if ($apiToken->isExpired()) {
            return null;
        }

        // Update last used timestamp
        $apiToken->markAsUsed();
        $this->repository->save($apiToken);

        return $apiToken;
    }

    /**
     * Revoke a token by ID
     *
     * @param int $tokenId
     * @return bool
     */
    public function revoke(int $tokenId): bool
    {
        return $this->repository->delete($tokenId);
    }

    /**
     * Revoke all tokens for a user
     *
     * @param $userId
     * @return int Number of tokens revoked
     */
    public function revokeAllForUser($userId): int
    {
        return $this->repository->deleteAllForUser($userId);
    }

    /**
     * Get all tokens for a user
     *
     * @param $userId
     * @return array<ApiToken>
     */
    public function getAllForUser($userId): array
    {
        return $this->repository->findAllForUser($userId);
    }

    /**
     * Clean up expired tokens
     *
     * @return int Number of tokens deleted
     */
    public function cleanupExpired(): int
    {
        return $this->repository->deleteExpired();
    }
}
