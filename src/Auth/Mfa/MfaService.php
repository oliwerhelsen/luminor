<?php

declare(strict_types=1);

namespace Luminor\Auth\Mfa;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;

/**
 * Multi-Factor Authentication Service
 */
class MfaService
{
    private TotpService $totpService;
    private MfaRepository $repository;

    public function __construct(TotpService $totpService, MfaRepository $repository)
    {
        $this->totpService = $totpService;
        $this->repository = $repository;
    }

    /**
     * Enable MFA for a user
     *
     * @param AuthenticatableInterface $user
     * @return array ['secret' => string, 'qr_code_uri' => string, 'recovery_codes' => array]
     */
    public function enable(AuthenticatableInterface $user): array
    {
        $secret = $this->totpService->generateSecret();
        $recoveryCodes = $this->generateRecoveryCodes();

        // Get user identifier (email or username)
        $accountName = method_exists($user, 'getEmail') ? $user->getEmail() : 'user';

        $qrCodeUri = $this->totpService->getQrCodeUri($secret, $accountName);

        // Store secret and recovery codes
        $this->repository->storeMfaData(
            $user->getAuthIdentifier(),
            $secret,
            $recoveryCodes
        );

        return [
            'secret' => $secret,
            'qr_code_uri' => $qrCodeUri,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Confirm MFA setup with a verification code
     *
     * @param AuthenticatableInterface $user
     * @param string $code
     * @return bool
     * @throws AuthenticationException
     */
    public function confirm(AuthenticatableInterface $user, string $code): bool
    {
        $mfaData = $this->repository->getMfaData($user->getAuthIdentifier());

        if (!$mfaData || !isset($mfaData['secret'])) {
            throw new AuthenticationException('MFA not set up for this user');
        }

        if (!$this->totpService->verify($code, $mfaData['secret'])) {
            throw AuthenticationException::invalidToken('Invalid verification code');
        }

        // Mark as confirmed
        $this->repository->confirmMfa($user->getAuthIdentifier());

        return true;
    }

    /**
     * Verify MFA code
     *
     * @param AuthenticatableInterface $user
     * @param string $code
     * @return bool
     */
    public function verify(AuthenticatableInterface $user, string $code): bool
    {
        $mfaData = $this->repository->getMfaData($user->getAuthIdentifier());

        if (!$mfaData || !$mfaData['confirmed']) {
            return false;
        }

        // Check if it's a recovery code
        if ($this->verifyRecoveryCode($user, $code)) {
            return true;
        }

        // Check TOTP code
        return $this->totpService->verify($code, $mfaData['secret']);
    }

    /**
     * Verify and consume a recovery code
     *
     * @param AuthenticatableInterface $user
     * @param string $code
     * @return bool
     */
    public function verifyRecoveryCode(AuthenticatableInterface $user, string $code): bool
    {
        $mfaData = $this->repository->getMfaData($user->getAuthIdentifier());

        if (!$mfaData || empty($mfaData['recovery_codes'])) {
            return false;
        }

        $hashedCode = hash('sha256', $code);

        foreach ($mfaData['recovery_codes'] as $index => $storedHash) {
            if (hash_equals($storedHash, $hashedCode)) {
                // Remove used recovery code
                $this->repository->consumeRecoveryCode($user->getAuthIdentifier(), $index);
                return true;
            }
        }

        return false;
    }

    /**
     * Disable MFA for a user
     *
     * @param AuthenticatableInterface $user
     * @return void
     */
    public function disable(AuthenticatableInterface $user): void
    {
        $this->repository->deleteMfaData($user->getAuthIdentifier());
    }

    /**
     * Check if user has MFA enabled
     *
     * @param AuthenticatableInterface $user
     * @return bool
     */
    public function isEnabled(AuthenticatableInterface $user): bool
    {
        $mfaData = $this->repository->getMfaData($user->getAuthIdentifier());
        return $mfaData && $mfaData['confirmed'];
    }

    /**
     * Regenerate recovery codes
     *
     * @param AuthenticatableInterface $user
     * @return array
     */
    public function regenerateRecoveryCodes(AuthenticatableInterface $user): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        $this->repository->updateRecoveryCodes($user->getAuthIdentifier(), $recoveryCodes);

        return array_keys($recoveryCodes);
    }

    /**
     * Generate recovery codes
     *
     * @param int $count
     * @return array Hash map of code => hashed_code
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(5))); // 10 character codes
            $codes[$code] = hash('sha256', $code);
        }

        return $codes;
    }
}
