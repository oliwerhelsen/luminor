<?php

declare(strict_types=1);

namespace Luminor\Tests\Auth;

use Luminor\Auth\Mfa\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        $this->totpService = new TotpService();
    }

    public function testGenerateSecret(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testGenerateCode(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = 1234567890;

        $code = $this->totpService->generateCode($secret, $timestamp);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertEquals(6, strlen($code));
    }

    public function testVerifyValidCode(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->generateCode($secret);

        $this->assertTrue($this->totpService->verify($code, $secret));
    }

    public function testVerifyInvalidCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertFalse($this->totpService->verify('000000', $secret));
        $this->assertFalse($this->totpService->verify('999999', $secret));
    }

    public function testVerifyWithTimeWindow(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = time();

        // Generate code for 30 seconds ago
        $oldCode = $this->totpService->generateCode($secret, $timestamp - 30);

        // Should still verify with window of 1
        $this->assertTrue($this->totpService->verify($oldCode, $secret, 1));

        // Generate code for 90 seconds ago
        $veryOldCode = $this->totpService->generateCode($secret, $timestamp - 90);

        // Should not verify with window of 1
        $this->assertFalse($this->totpService->verify($veryOldCode, $secret, 1));
    }

    public function testGetQrCodeUri(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $this->totpService->getQrCodeUri($secret, 'user@example.com', 'TestApp');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=TestApp', $uri);
        $this->assertStringContainsString('user@example.com', $uri);
    }

    public function testConsistentCodeGeneration(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = 1234567890;

        $code1 = $this->totpService->generateCode($secret, $timestamp);
        $code2 = $this->totpService->generateCode($secret, $timestamp);

        // Same secret and timestamp should produce same code
        $this->assertEquals($code1, $code2);
    }
}
