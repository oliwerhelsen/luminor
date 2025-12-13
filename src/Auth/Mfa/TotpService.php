<?php

declare(strict_types=1);

namespace Luminor\Auth\Mfa;

/**
 * TOTP (Time-based One-Time Password) Service
 * Implements RFC 6238 for 2FA
 */
class TotpService
{
    private int $period = 30; // 30 seconds
    private int $digits = 6;
    private string $algorithm = 'sha1';

    /**
     * Generate a secret key
     *
     * @param int $length
     * @return string Base32 encoded secret
     */
    public function generateSecret(int $length = 32): string
    {
        $secret = random_bytes($length);
        return $this->base32Encode($secret);
    }

    /**
     * Generate current TOTP code
     *
     * @param string $secret Base32 encoded secret
     * @param int|null $timestamp Override current time
     * @return string 6-digit code
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeCounter = floor($timestamp / $this->period);

        return $this->generateHotp($secret, $timeCounter);
    }

    /**
     * Verify a TOTP code
     *
     * @param string $code User-provided code
     * @param string $secret Base32 encoded secret
     * @param int $window Number of periods to check (allows time drift)
     * @return bool
     */
    public function verify(string $code, string $secret, int $window = 1): bool
    {
        $timestamp = time();
        $timeCounter = floor($timestamp / $this->period);

        // Check current period and nearby periods (to account for time drift)
        for ($i = -$window; $i <= $window; $i++) {
            $generatedCode = $this->generateHotp($secret, $timeCounter + $i);

            if (hash_equals($generatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate QR code URI for authenticator apps
     *
     * @param string $secret
     * @param string $accountName User identifier (email)
     * @param string $issuer Application name
     * @return string otpauth:// URI
     */
    public function getQrCodeUri(string $secret, string $accountName, string $issuer = 'Luminor'): string
    {
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ];

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            http_build_query($params)
        );
    }

    /**
     * Generate HOTP code (counter-based)
     *
     * @param string $secret
     * @param int $counter
     * @return string
     */
    private function generateHotp(string $secret, int $counter): string
    {
        $secretKey = $this->base32Decode($secret);

        // Convert counter to binary string (8 bytes, big-endian)
        $counterBytes = pack('N*', 0, $counter);

        // Generate HMAC
        $hash = hash_hmac($this->algorithm, $counterBytes, $secretKey, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        // Generate code
        $code = $truncated % (10 ** $this->digits);

        return str_pad((string)$code, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encode
     *
     * @param string $data
     * @return string
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $bits = '';

        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= $alphabet[bindec($chunk)];
        }

        return $result;
    }

    /**
     * Base32 decode
     *
     * @param string $data
     * @return string
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $bits = '';

        foreach (str_split($data) as $char) {
            $val = strpos($alphabet, $char);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $result .= chr(bindec($chunk));
        }

        return $result;
    }
}
