<?php

declare(strict_types=1);

namespace Luminor\Auth\ApiToken;

/**
 * API Token Repository Interface
 */
interface ApiTokenRepository
{
    /**
     * Save an API token
     *
     * @param ApiToken $token
     * @return void
     */
    public function save(ApiToken $token): void;

    /**
     * Find token by hashed token string
     *
     * @param string $hashedToken
     * @return ApiToken|null
     */
    public function findByHashedToken(string $hashedToken): ?ApiToken;

    /**
     * Find token by ID
     *
     * @param int $id
     * @return ApiToken|null
     */
    public function findById(int $id): ?ApiToken;

    /**
     * Find all tokens for a user
     *
     * @param mixed $userId
     * @return array<ApiToken>
     */
    public function findAllForUser($userId): array;

    /**
     * Delete a token by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Delete all tokens for a user
     *
     * @param mixed $userId
     * @return int Number of tokens deleted
     */
    public function deleteAllForUser($userId): int;

    /**
     * Delete all expired tokens
     *
     * @return int Number of tokens deleted
     */
    public function deleteExpired(): int;
}
