<?php

declare(strict_types=1);

namespace Luminor\Auth\RateLimit;

use Luminor\Http\Middleware;
use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Rate Limiting Middleware
 */
class RateLimitMiddleware implements Middleware
{
    private RateLimiter $limiter;
    private int $maxAttempts;
    private string $keyPrefix;

    public function __construct(RateLimiter $limiter, int $maxAttempts = 60, string $keyPrefix = 'rate_limit')
    {
        $this->limiter = $limiter;
        $this->maxAttempts = $maxAttempts;
        $this->keyPrefix = $keyPrefix;
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            return $this->buildResponse($key);
        }

        $this->limiter->hit($key);

        $response = $next($request);

        return $this->addHeaders($response, $key);
    }

    /**
     * Resolve the rate limit key for the request
     *
     * @param Request $request
     * @return string
     */
    protected function resolveKey(Request $request): string
    {
        $identifier = $request->ip() ?? 'unknown';
        return $this->keyPrefix . ':' . $identifier;
    }

    /**
     * Build rate limit exceeded response
     *
     * @param string $key
     * @return Response
     */
    protected function buildResponse(string $key): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return Response::json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429)->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', '0');
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param string $key
     * @return Response
     */
    protected function addHeaders(Response $response, string $key): Response
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string)$this->limiter->remaining($key, $this->maxAttempts));
    }
}
