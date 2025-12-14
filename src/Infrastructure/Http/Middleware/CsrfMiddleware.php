<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http\Middleware;

use Luminor\DDD\Session\SessionInterface;
use Luminor\DDD\Security\Csrf\CsrfToken;
use Luminor\DDD\Security\Csrf\CsrfException;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

/**
 * CSRF Protection Middleware
 *
 * Protects against Cross-Site Request Forgery attacks.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private SessionInterface $session;

    /** @var array<string> Methods that require CSRF protection */
    private array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array<string> URIs to exclude from CSRF protection */
    private array $except = [];

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Add URIs to exclude from CSRF protection.
     *
     * @param array<string> $uris
     */
    public function except(array $uris): self
    {
        $this->except = array_merge($this->except, $uris);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        // Ensure session is started
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        // Generate token if not exists
        if (!$this->session->has('_csrf_token')) {
            CsrfToken::regenerate($this->session);
        }

        // Check if this request should be protected
        if ($this->shouldProtect($request)) {
            $this->validateCsrfToken($request);
        }

        return $next($request, $response);
    }

    /**
     * Check if the request should be protected.
     */
    private function shouldProtect(Request $request): bool
    {
        $method = $request->getMethod();

        // Only protect specific HTTP methods
        if (!in_array(strtoupper($method), $this->protectedMethods, true)) {
            return false;
        }

        // Check if URI is in exception list
        $uri = $request->getURI();
        foreach ($this->except as $pattern) {
            if ($this->uriMatches($pattern, $uri)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate the CSRF token.
     *
     * @throws CsrfException
     */
    private function validateCsrfToken(Request $request): void
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = CsrfToken::getFromSession($this->session);

        if ($sessionToken === null || !CsrfToken::verify($token, $sessionToken)) {
            throw new CsrfException();
        }
    }

    /**
     * Get the CSRF token from the request.
     */
    private function getTokenFromRequest(Request $request): string
    {
        // Check POST/PUT data
        $token = $request->getParam('_csrf_token', '');

        if ($token !== '') {
            return $token;
        }

        // Check headers
        $token = $request->getHeader('x-csrf-token', '');

        if ($token !== '') {
            return $token;
        }

        $token = $request->getHeader('x-xsrf-token', '');

        return $token;
    }

    /**
     * Check if a URI matches a pattern.
     */
    private function uriMatches(string $pattern, string $uri): bool
    {
        // Convert pattern to regex
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '#^' . $pattern . '$#';

        return preg_match($pattern, $uri) === 1;
    }

    /**
     * Get the current CSRF token.
     */
    public function getToken(): ?string
    {
        return CsrfToken::getFromSession($this->session);
    }
}
