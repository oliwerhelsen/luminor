<?php

declare(strict_types=1);

namespace Luminor\DDD\Http;

/**
 * Cookie
 *
 * Represents an HTTP cookie with all its attributes.
 */
final class Cookie
{
    private string $name;

    private string $value;

    private int $expire;

    private string $path;

    private string $domain;

    private bool $secure;

    private bool $httpOnly;

    private string $sameSite;

    public function __construct(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expire = $expire;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }

    /**
     * Create a cookie that expires in the given minutes.
     */
    public static function make(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        $expire = $minutes > 0 ? time() + ($minutes * 60) : 0;

        return new self(
            $name,
            $value,
            $expire,
            $path,
            $domain ?? '',
            $secure ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            $httpOnly,
            $sameSite,
        );
    }

    /**
     * Create a cookie that lasts forever (5 years).
     */
    public static function forever(
        string $name,
        string $value,
        string $path = '/',
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        return self::make($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Create a cookie that expires immediately (for deletion).
     */
    public static function forget(string $name, string $path = '/', ?string $domain = null): self
    {
        return new self($name, '', time() - 3600, $path, $domain ?? '');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpire(): int
    {
        return $this->expire;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * Send the cookie to the browser.
     */
    public function send(): bool
    {
        return setcookie(
            $this->name,
            $this->value,
            [
                'expires' => $this->expire,
                'path' => $this->path,
                'domain' => $this->domain,
                'secure' => $this->secure,
                'httponly' => $this->httpOnly,
                'samesite' => $this->sameSite,
            ],
        );
    }

    /**
     * Convert cookie to an array.
     *
     * @return array{name: string, value: string, expire: int, path: string, domain: string, secure: bool, httpOnly: bool, sameSite: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'expire' => $this->expire,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httpOnly' => $this->httpOnly,
            'sameSite' => $this->sameSite,
        ];
    }
}
