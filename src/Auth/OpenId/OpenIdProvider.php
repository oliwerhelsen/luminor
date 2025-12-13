<?php

declare(strict_types=1);

namespace Luminor\Auth\OpenId;

/**
 * OpenID Connect Provider Configuration
 */
class OpenIdProvider
{
    private string $name;
    private string $clientId;
    private string $clientSecret;
    private string $discoveryUrl;
    private string $redirectUri;
    private array $scopes;
    private ?array $metadata = null;

    public function __construct(
        string $name,
        string $clientId,
        string $clientSecret,
        string $discoveryUrl,
        string $redirectUri,
        array $scopes = ['openid', 'profile', 'email']
    ) {
        $this->name = $name;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->discoveryUrl = $discoveryUrl;
        $this->redirectUri = $redirectUri;
        $this->scopes = $scopes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getDiscoveryUrl(): string
    {
        return $this->discoveryUrl;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Get provider metadata from discovery endpoint
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getMetadata(): array
    {
        if ($this->metadata === null) {
            $this->metadata = $this->fetchMetadata();
        }

        return $this->metadata;
    }

    /**
     * Get authorization endpoint URL
     *
     * @return string
     */
    public function getAuthorizationEndpoint(): string
    {
        return $this->getMetadata()['authorization_endpoint'] ?? '';
    }

    /**
     * Get token endpoint URL
     *
     * @return string
     */
    public function getTokenEndpoint(): string
    {
        return $this->getMetadata()['token_endpoint'] ?? '';
    }

    /**
     * Get userinfo endpoint URL
     *
     * @return string
     */
    public function getUserInfoEndpoint(): string
    {
        return $this->getMetadata()['userinfo_endpoint'] ?? '';
    }

    /**
     * Get JWKS URI
     *
     * @return string
     */
    public function getJwksUri(): string
    {
        return $this->getMetadata()['jwks_uri'] ?? '';
    }

    /**
     * Fetch provider metadata from discovery endpoint
     *
     * @return array
     * @throws \RuntimeException
     */
    private function fetchMetadata(): array
    {
        $ch = curl_init($this->discoveryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Failed to fetch OIDC metadata: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("OIDC discovery endpoint returned HTTP {$httpCode}");
        }

        $metadata = json_decode($response, true);

        if (!is_array($metadata)) {
            throw new \RuntimeException('Invalid OIDC metadata response');
        }

        return $metadata;
    }

    /**
     * Create provider from common services
     */
    public static function azure(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): self {
        return new self(
            'azure',
            $clientId,
            $clientSecret,
            "https://login.microsoftonline.com/{$tenantId}/v2.0/.well-known/openid-configuration",
            $redirectUri,
            ['openid', 'profile', 'email']
        );
    }

    public static function google(
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): self {
        return new self(
            'google',
            $clientId,
            $clientSecret,
            'https://accounts.google.com/.well-known/openid-configuration',
            $redirectUri,
            ['openid', 'profile', 'email']
        );
    }

    public static function okta(
        string $domain,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): self {
        return new self(
            'okta',
            $clientId,
            $clientSecret,
            "https://{$domain}/.well-known/openid-configuration",
            $redirectUri,
            ['openid', 'profile', 'email']
        );
    }
}
