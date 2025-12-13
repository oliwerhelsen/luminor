<?php

declare(strict_types=1);

namespace Luminor\Auth\Mfa;

/**
 * MFA Data Repository Interface
 */
interface MfaRepository
{
    /**
     * Store MFA data for a user
     *
     * @param mixed $userId
     * @param string $secret
     * @param array $recoveryCodes
     * @return void
     */
    public function storeMfaData($userId, string $secret, array $recoveryCodes): void;

    /**
     * Get MFA data for a user
     *
     * @param mixed $userId
     * @return array|null ['secret' => string, 'confirmed' => bool, 'recovery_codes' => array]
     */
    public function getMfaData($userId): ?array;

    /**
     * Mark MFA as confirmed
     *
     * @param mixed $userId
     * @return void
     */
    public function confirmMfa($userId): void;

    /**
     * Delete MFA data
     *
     * @param mixed $userId
     * @return void
     */
    public function deleteMfaData($userId): void;

    /**
     * Consume a recovery code (remove it from available codes)
     *
     * @param mixed $userId
     * @param int $codeIndex
     * @return void
     */
    public function consumeRecoveryCode($userId, int $codeIndex): void;

    /**
     * Update recovery codes
     *
     * @param mixed $userId
     * @param array $recoveryCodes
     * @return void
     */
    public function updateRecoveryCodes($userId, array $recoveryCodes): void;
}
