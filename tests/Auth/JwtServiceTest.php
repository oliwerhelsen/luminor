<?php

declare(strict_types=1);

namespace Luminor\Tests\Auth;

use Luminor\Auth\AuthenticationException;
use Luminor\Auth\Jwt\JwtService;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        $this->jwtService = new JwtService('this-is-a-very-secure-secret-key-for-testing', 3600, 'test-issuer');
    }

    public function testGenerateToken(): void
    {
        $token = $this->jwtService->generate('user123', ['role' => 'admin']);

        $this->assertNotEmpty($token->getToken());
        $this->assertEquals('user123', $token->getSubject());
        $this->assertEquals('admin', $token->getClaim('role'));
        $this->assertEquals('test-issuer', $token->getClaim('iss'));
    }

    public function testParseValidToken(): void
    {
        $original = $this->jwtService->generate('user456', ['email' => 'test@example.com']);
        $parsed = $this->jwtService->parse($original->getToken());

        $this->assertEquals('user456', $parsed->getSubject());
        $this->assertEquals('test@example.com', $parsed->getClaim('email'));
        $this->assertFalse($parsed->isExpired());
    }

    public function testParseInvalidToken(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->jwtService->parse('invalid.token.here');
    }

    public function testParseTamperedToken(): void
    {
        $token = $this->jwtService->generate('user789');
        $parts = explode('.', $token->getToken());

        // Tamper with payload
        $parts[1] = base64_encode('{"sub":"attacker"}');
        $tamperedToken = implode('.', $parts);

        $this->expectException(AuthenticationException::class);
        $this->jwtService->parse($tamperedToken);
    }

    public function testExpiredToken(): void
    {
        $shortLivedService = new JwtService('this-is-a-very-secure-secret-key-for-testing', -1);
        $token = $shortLivedService->generate('user999');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('expired');
        $shortLivedService->parse($token->getToken());
    }

    public function testRefreshToken(): void
    {
        $refreshToken = $this->jwtService->generateRefreshToken('user123');

        $this->assertEquals('user123', $refreshToken->getSubject());
        $this->assertEquals('refresh', $refreshToken->getClaim('type'));
        $this->assertGreaterThan(time() + (86400 * 29), $refreshToken->getExpiresAt());
    }

    public function testTokenExpiry(): void
    {
        $token = $this->jwtService->generate('user123');

        $this->assertFalse($token->isExpired());
        $this->assertGreaterThan(0, $token->getTimeToExpiry());
    }
}
