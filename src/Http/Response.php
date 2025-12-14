<?php

declare(strict_types=1);

namespace Luminor\DDD\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * HTTP Response wrapper providing a clean API over Symfony HttpFoundation.
 *
 * Provides convenient methods for building HTTP responses including JSON,
 * redirects, cookies, and more.
 */
class Response
{
    // Common HTTP status codes
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENT_REDIRECT = 308;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;

    private SymfonyResponse $symfonyResponse;
    private bool $sent = false;

    public function __construct(?SymfonyResponse $response = null)
    {
        $this->symfonyResponse = $response ?? new SymfonyResponse();
    }

    /**
     * Get the underlying Symfony response.
     */
    public function getSymfonyResponse(): SymfonyResponse
    {
        return $this->symfonyResponse;
    }

    /**
     * Set the HTTP status code.
     */
    public function setStatusCode(int $code, ?string $text = null): self
    {
        if ($text !== null) {
            $this->symfonyResponse->setStatusCode($code, $text);
        } else {
            $this->symfonyResponse->setStatusCode($code);
        }

        return $this;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->symfonyResponse->getStatusCode();
    }

    /**
     * Set the response content.
     */
    public function setContent(string $content): self
    {
        $this->symfonyResponse->setContent($content);
        return $this;
    }

    /**
     * Get the response content.
     */
    public function getContent(): string
    {
        return (string) $this->symfonyResponse->getContent();
    }

    /**
     * Send a JSON response.
     *
     * @param array<mixed>|object $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param int $options JSON encoding options
     */
    public function json(array|object $data, int $status = 200, int $options = 0): self
    {
        $jsonResponse = new JsonResponse($data, $status, [], false);

        if ($options !== 0) {
            $jsonResponse->setEncodingOptions($options);
        }

        $this->symfonyResponse = $jsonResponse;
        return $this;
    }

    /**
     * Send a 204 No Content response.
     */
    public function noContent(): self
    {
        $this->symfonyResponse->setStatusCode(204);
        $this->symfonyResponse->setContent('');
        return $this;
    }

    /**
     * Add a header to the response.
     */
    public function addHeader(string $name, string $value): self
    {
        $this->symfonyResponse->headers->set($name, $value);
        return $this;
    }

    /**
     * Set a header (alias for addHeader).
     */
    public function setHeader(string $name, string $value): self
    {
        return $this->addHeader($name, $value);
    }

    /**
     * Get a response header.
     */
    public function getHeader(string $name): ?string
    {
        return $this->symfonyResponse->headers->get($name);
    }

    /**
     * Check if a header exists.
     */
    public function hasHeader(string $name): bool
    {
        return $this->symfonyResponse->headers->has($name);
    }

    /**
     * Remove a header.
     */
    public function removeHeader(string $name): self
    {
        $this->symfonyResponse->headers->remove($name);
        return $this;
    }

    /**
     * Get all headers.
     *
     * @return array<string, array<string>>
     */
    public function getHeaders(): array
    {
        return $this->symfonyResponse->headers->all();
    }

    /**
     * Set the Content-Type header.
     */
    public function setContentType(string $contentType, ?string $charset = null): self
    {
        $value = $contentType;
        if ($charset !== null) {
            $value .= '; charset=' . $charset;
        }

        $this->symfonyResponse->headers->set('Content-Type', $value);
        return $this;
    }

    /**
     * Add a cookie to the response.
     */
    public function setCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        ?string $sameSite = 'lax'
    ): self {
        $cookie = Cookie::create($name)
            ->withValue($value)
            ->withExpires($expire)
            ->withPath($path)
            ->withDomain($domain)
            ->withSecure($secure)
            ->withHttpOnly($httpOnly)
            ->withSameSite($sameSite);

        $this->symfonyResponse->headers->setCookie($cookie);
        return $this;
    }

    /**
     * Remove/clear a cookie.
     */
    public function clearCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        $this->symfonyResponse->headers->clearCookie($name, $path, $domain);
        return $this;
    }

    /**
     * Set a redirect response.
     */
    public function redirect(string $url, int $status = 302): self
    {
        $this->symfonyResponse->setStatusCode($status);
        $this->symfonyResponse->headers->set('Location', $url);
        return $this;
    }

    /**
     * Set cache headers.
     */
    public function setCache(int $maxAge, bool $public = true): self
    {
        if ($public) {
            $this->symfonyResponse->setPublic();
        } else {
            $this->symfonyResponse->setPrivate();
        }

        $this->symfonyResponse->setMaxAge($maxAge);
        return $this;
    }

    /**
     * Disable caching.
     */
    public function noCache(): self
    {
        $this->symfonyResponse->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->symfonyResponse->headers->set('Pragma', 'no-cache');
        $this->symfonyResponse->headers->set('Expires', '0');
        return $this;
    }

    /**
     * Send the response.
     */
    public function send(): self
    {
        if (!$this->sent) {
            $this->symfonyResponse->send();
            $this->sent = true;
        }

        return $this;
    }

    /**
     * Check if the response has been sent.
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Prepare the response for sending.
     */
    public function prepare(Request $request): self
    {
        $this->symfonyResponse->prepare($request->getSymfonyRequest());
        return $this;
    }

    /**
     * Check if the response is successful (2xx).
     */
    public function isSuccessful(): bool
    {
        return $this->symfonyResponse->isSuccessful();
    }

    /**
     * Check if the response is a redirect (3xx).
     */
    public function isRedirect(): bool
    {
        return $this->symfonyResponse->isRedirection();
    }

    /**
     * Check if the response is a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->symfonyResponse->isClientError();
    }

    /**
     * Check if the response is a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->symfonyResponse->isServerError();
    }
}
