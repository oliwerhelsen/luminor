<?php

declare(strict_types=1);

namespace Luminor\Auth\ApiToken;

/**
 * API Token entity
 */
class ApiToken
{
    private ?int $id;
    private $userId;
    private string $name;
    private string $token;
    private ?string $hashedToken;
    private array $scopes;
    private ?int $expiresAt;
    private ?int $lastUsedAt;
    private int $createdAt;

    public function __construct(
        $userId,
        string $name,
        string $token,
        array $scopes = [],
        ?int $expiresAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->token = $token;
        $this->hashedToken = hash('sha256', $token);
        $this->scopes = $scopes;
        $this->expiresAt = $expiresAt;
        $this->lastUsedAt = null;
        $this->createdAt = time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && time() >= $this->expiresAt;
    }

    public function getLastUsedAt(): ?int
    {
        return $this->lastUsedAt;
    }

    public function markAsUsed(): void
    {
        $this->lastUsedAt = time();
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * Generate a new API token
     *
     * @param int $length
     * @return string
     */
    public static function generate(int $length = 40): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash a token for storage
     *
     * @param string $token
     * @return string
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
