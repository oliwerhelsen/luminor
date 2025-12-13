<?php

declare(strict_types=1);

namespace Luminor\Auth\Jwt;

use Luminor\Auth\AuthenticationException;

/**
 * JWT token generation and validation service
 * Uses HS256 (HMAC-SHA256) algorithm
 */
class JwtService
{
    private string $secret;
    private int $ttl; // Time to live in seconds
    private string $issuer;
    private string $algorithm = 'HS256';

    public function __construct(string $secret, int $ttl = 3600, string $issuer = 'luminor')
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 characters');
        }

        $this->secret = $secret;
        $this->ttl = $ttl;
        $this->issuer = $issuer;
    }

    /**
     * Generate a JWT token
     *
     * @param string|int $subject User identifier
     * @param array $claims Additional claims to include
     * @return JwtToken
     */
    public function generate($subject, array $claims = []): JwtToken
    {
        $now = time();
        $expiresAt = $now + $this->ttl;

        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'sub' => (string)$subject,
            'iat' => $now,
            'exp' => $expiresAt,
            'nbf' => $now,
        ]);

        $token = $this->encode($payload);

        return new JwtToken($token, $payload, $expiresAt);
    }

    /**
     * Parse and validate a JWT token
     *
     * @param string $token
     * @return JwtToken
     * @throws AuthenticationException
     */
    public function parse(string $token): JwtToken
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw AuthenticationException::invalidToken('Invalid JWT format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        if (!hash_equals($expectedSignature, $signature)) {
            throw AuthenticationException::invalidToken('Invalid JWT signature');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            throw AuthenticationException::invalidToken('Invalid JWT payload');
        }

        // Validate claims
        $this->validateClaims($payload);

        return new JwtToken($token, $payload, $payload['exp']);
    }

    /**
     * Generate a refresh token
     *
     * @param string|int $subject
     * @return JwtToken
     */
    public function generateRefreshToken($subject): JwtToken
    {
        $now = time();
        $expiresAt = $now + (86400 * 30); // 30 days

        $payload = [
            'iss' => $this->issuer,
            'sub' => (string)$subject,
            'iat' => $now,
            'exp' => $expiresAt,
            'type' => 'refresh',
        ];

        $token = $this->encode($payload);

        return new JwtToken($token, $payload, $expiresAt);
    }

    /**
     * Validate JWT claims
     *
     * @param array $payload
     * @throws AuthenticationException
     */
    private function validateClaims(array $payload): void
    {
        $now = time();

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < $now) {
            throw AuthenticationException::tokenExpired();
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw AuthenticationException::invalidToken('Token not yet valid');
        }

        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            throw AuthenticationException::invalidToken('Invalid token issuer');
        }

        // Check subject exists
        if (!isset($payload['sub'])) {
            throw AuthenticationException::invalidToken('Token missing subject');
        }
    }

    /**
     * Encode payload to JWT
     *
     * @param array $payload
     * @return string
     */
    private function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Sign data using HMAC
     *
     * @param string $data
     * @return string
     */
    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    /**
     * Base64 URL encode
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     *
     * @param string $data
     * @return string
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get TTL in seconds
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Set TTL in seconds
     *
     * @param int $ttl
     * @return self
     */
    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }
}
