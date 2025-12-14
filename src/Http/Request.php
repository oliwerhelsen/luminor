<?php

declare(strict_types=1);

namespace Luminor\DDD\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * HTTP Request wrapper providing a clean API over Symfony HttpFoundation.
 *
 * This class maintains API compatibility with the previous Utopia HTTP integration
 * while using Symfony HttpFoundation under the hood.
 */
class Request
{
    private SymfonyRequest $symfonyRequest;

    /** @var array<string, mixed> Cached decoded JSON payload */
    private ?array $jsonPayload = null;

    public function __construct(?SymfonyRequest $request = null)
    {
        $this->symfonyRequest = $request ?? SymfonyRequest::createFromGlobals();
    }

    /**
     * Create a request from PHP globals.
     */
    public static function createFromGlobals(): self
    {
        return new self(SymfonyRequest::createFromGlobals());
    }

    /**
     * Create a request for testing or internal use.
     *
     * @param array<string, mixed> $query GET parameters
     * @param array<string, mixed> $request POST parameters
     * @param array<string, mixed> $attributes Request attributes
     * @param array<string, mixed> $cookies Cookies
     * @param array<string, mixed> $files Uploaded files
     * @param array<string, mixed> $server Server parameters
     * @param string|null $content Raw body content
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): self {
        $symfonyRequest = SymfonyRequest::create(
            $uri,
            $method,
            array_merge($query, $request),
            $cookies,
            $files,
            $server,
            $content
        );

        return new self($symfonyRequest);
    }

    /**
     * Get the underlying Symfony request.
     */
    public function getSymfonyRequest(): SymfonyRequest
    {
        return $this->symfonyRequest;
    }

    /**
     * Get a parameter from query string, request body, or route parameters.
     *
     * @param mixed $default Default value if parameter not found
     */
    public function getParam(string $name, mixed $default = null): mixed
    {
        // First check route parameters (attributes)
        if ($this->symfonyRequest->attributes->has($name)) {
            return $this->symfonyRequest->attributes->get($name);
        }

        // Then check query parameters
        if ($this->symfonyRequest->query->has($name)) {
            return $this->symfonyRequest->query->get($name);
        }

        // Then check request body (POST/PUT etc)
        if ($this->symfonyRequest->request->has($name)) {
            return $this->symfonyRequest->request->get($name);
        }

        // Finally check JSON payload
        $json = $this->getJsonPayload();
        if (isset($json[$name])) {
            return $json[$name];
        }

        return $default;
    }

    /**
     * Get all parameters merged from query, request, and JSON body.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge(
            $this->symfonyRequest->query->all(),
            $this->symfonyRequest->request->all(),
            $this->getJsonPayload(),
            $this->symfonyRequest->attributes->all()
        );
    }

    /**
     * Get the raw request payload/body.
     */
    public function getPayload(): string
    {
        return $this->symfonyRequest->getContent();
    }

    /**
     * Get the parsed JSON payload.
     *
     * @return array<string, mixed>
     */
    public function getJsonPayload(): array
    {
        if ($this->jsonPayload !== null) {
            return $this->jsonPayload;
        }

        $content = $this->symfonyRequest->getContent();

        if (empty($content)) {
            return $this->jsonPayload = [];
        }

        $contentType = $this->symfonyRequest->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            return $this->jsonPayload = [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return $this->jsonPayload = is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return $this->jsonPayload = [];
        }
    }

    /**
     * Get the HTTP method (GET, POST, PUT, PATCH, DELETE, etc).
     */
    public function getMethod(): string
    {
        return $this->symfonyRequest->getMethod();
    }

    /**
     * Check if the request method matches.
     */
    public function isMethod(string $method): bool
    {
        return $this->symfonyRequest->isMethod($method);
    }

    /**
     * Get a request header.
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->symfonyRequest->headers->get($name, $default);
    }

    /**
     * Check if a header exists.
     */
    public function hasHeader(string $name): bool
    {
        return $this->symfonyRequest->headers->has($name);
    }

    /**
     * Get all headers.
     *
     * @return array<string, string|array<string>>
     */
    public function getHeaders(): array
    {
        return $this->symfonyRequest->headers->all();
    }

    /**
     * Get the request URI path.
     */
    public function getUri(): string
    {
        return $this->symfonyRequest->getPathInfo();
    }

    /**
     * Get the full request URI including query string.
     */
    public function getFullUri(): string
    {
        return $this->symfonyRequest->getRequestUri();
    }

    /**
     * Get the request scheme (http or https).
     */
    public function getScheme(): string
    {
        return $this->symfonyRequest->getScheme();
    }

    /**
     * Get the host name.
     */
    public function getHost(): string
    {
        return $this->symfonyRequest->getHost();
    }

    /**
     * Get the full URL.
     */
    public function getUrl(): string
    {
        return $this->symfonyRequest->getUri();
    }

    /**
     * Get query string parameters.
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->symfonyRequest->query->all();
    }

    /**
     * Get a query string parameter.
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->symfonyRequest->query->get($name, $default);
    }

    /**
     * Get POST/request body parameters.
     *
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return $this->symfonyRequest->request->all();
    }

    /**
     * Get a cookie value.
     */
    public function getCookie(string $name, ?string $default = null): ?string
    {
        return $this->symfonyRequest->cookies->get($name, $default);
    }

    /**
     * Get all cookies.
     *
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->symfonyRequest->cookies->all();
    }

    /**
     * Get the client IP address.
     */
    public function getClientIp(): ?string
    {
        return $this->symfonyRequest->getClientIp();
    }

    /**
     * Get a server parameter.
     */
    public function getServer(string $name, mixed $default = null): mixed
    {
        return $this->symfonyRequest->server->get($name, $default);
    }

    /**
     * Check if the request is an AJAX/XHR request.
     */
    public function isAjax(): bool
    {
        return $this->symfonyRequest->isXmlHttpRequest();
    }

    /**
     * Check if the request is secure (HTTPS).
     */
    public function isSecure(): bool
    {
        return $this->symfonyRequest->isSecure();
    }

    /**
     * Check if the request expects JSON response.
     */
    public function wantsJson(): bool
    {
        $acceptHeader = $this->getHeader('Accept', '');
        return str_contains($acceptHeader, 'application/json')
            || str_contains($acceptHeader, '*/*');
    }

    /**
     * Get the bearer token from Authorization header.
     */
    public function getBearerToken(): ?string
    {
        $header = $this->getHeader('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Set a route parameter/attribute.
     */
    public function setAttribute(string $name, mixed $value): self
    {
        $this->symfonyRequest->attributes->set($name, $value);
        return $this;
    }

    /**
     * Get a route parameter/attribute.
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->symfonyRequest->attributes->get($name, $default);
    }

    /**
     * Get uploaded files.
     *
     * @return array<string, \Symfony\Component\HttpFoundation\File\UploadedFile>
     */
    public function getFiles(): array
    {
        return $this->symfonyRequest->files->all();
    }

    /**
     * Get a specific uploaded file.
     */
    public function getFile(string $name): ?\Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->symfonyRequest->files->get($name);
    }
}
