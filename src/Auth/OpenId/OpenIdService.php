<?php

declare(strict_types=1);

namespace Luminor\Auth\OpenId;

use Luminor\Auth\AuthenticationException;

/**
 * OpenID Connect Service
 */
class OpenIdService
{
    private OpenIdProvider $provider;

    public function __construct(OpenIdProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Generate authorization URL for redirecting user to IDP
     *
     * @param string|null $state CSRF state token
     * @param string|null $nonce Nonce for ID token validation
     * @return string
     */
    public function getAuthorizationUrl(?string $state = null, ?string $nonce = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));
        $nonce = $nonce ?? bin2hex(random_bytes(16));

        $params = [
            'client_id' => $this->provider->getClientId(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->provider->getScopes()),
            'redirect_uri' => $this->provider->getRedirectUri(),
            'state' => $state,
            'nonce' => $nonce,
        ];

        return $this->provider->getAuthorizationEndpoint() . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     *
     * @param string $code Authorization code from IDP
     * @return array Token response with access_token, id_token, etc.
     * @throws AuthenticationException
     */
    public function exchangeCode(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->provider->getRedirectUri(),
            'client_id' => $this->provider->getClientId(),
            'client_secret' => $this->provider->getClientSecret(),
        ];

        $response = $this->httpPost($this->provider->getTokenEndpoint(), $params);

        if (!isset($response['access_token'])) {
            throw AuthenticationException::invalidToken('Failed to obtain access token');
        }

        return $response;
    }

    /**
     * Get user info from userinfo endpoint
     *
     * @param string $accessToken
     * @return array User claims
     * @throws AuthenticationException
     */
    public function getUserInfo(string $accessToken): array
    {
        $ch = curl_init($this->provider->getUserInfoEndpoint());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw AuthenticationException::invalidToken("Failed to fetch user info: {$error}");
        }

        if ($httpCode !== 200) {
            throw AuthenticationException::invalidToken("UserInfo endpoint returned HTTP {$httpCode}");
        }

        $userInfo = json_decode($response, true);

        if (!is_array($userInfo)) {
            throw AuthenticationException::invalidToken('Invalid user info response');
        }

        return $userInfo;
    }

    /**
     * Parse and validate ID token (basic validation)
     *
     * @param string $idToken JWT ID token
     * @return array Token claims
     * @throws AuthenticationException
     */
    public function parseIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw AuthenticationException::invalidToken('Invalid ID token format');
        }

        [, $payloadEncoded,] = $parts;

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            throw AuthenticationException::invalidToken('Invalid ID token payload');
        }

        // Basic validation
        $now = time();

        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw AuthenticationException::tokenExpired();
        }

        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw AuthenticationException::invalidToken('ID token not yet valid');
        }

        if (!isset($payload['sub'])) {
            throw AuthenticationException::invalidToken('ID token missing subject');
        }

        return $payload;
    }

    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshToken
     * @return array Token response
     * @throws AuthenticationException
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->provider->getClientId(),
            'client_secret' => $this->provider->getClientSecret(),
        ];

        $response = $this->httpPost($this->provider->getTokenEndpoint(), $params);

        if (!isset($response['access_token'])) {
            throw AuthenticationException::invalidToken('Failed to refresh access token');
        }

        return $response;
    }

    /**
     * Make HTTP POST request
     *
     * @param string $url
     * @param array $params
     * @return array
     * @throws AuthenticationException
     */
    private function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw AuthenticationException::invalidToken("HTTP request failed: {$error}");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw AuthenticationException::invalidToken('Invalid response from IDP');
        }

        if ($httpCode >= 400) {
            $errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            throw AuthenticationException::invalidToken("IDP error: {$errorMsg}");
        }

        return $data;
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

    public function getProvider(): OpenIdProvider
    {
        return $this->provider;
    }
}
