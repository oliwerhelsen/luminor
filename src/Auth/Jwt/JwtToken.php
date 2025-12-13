<?php

declare(strict_types=1);

namespace Luminor\Auth\Jwt;

/**
 * JWT Token data structure
 */
class JwtToken
{
    private string $token;
    private array $payload;
    private int $expiresAt;

    public function __construct(string $token, array $payload, int $expiresAt)
    {
        $this->token = $token;
        $this->payload = $payload;
        $this->expiresAt = $expiresAt;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function getSubject(): ?string
    {
        return $this->payload['sub'] ?? null;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    public function getTimeToExpiry(): int
    {
        return max(0, $this->expiresAt - time());
    }
}
