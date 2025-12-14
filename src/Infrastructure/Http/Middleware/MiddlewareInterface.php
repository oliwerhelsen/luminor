<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Http\Middleware;

use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Interface for HTTP middleware.
 *
 * Middleware can intercept HTTP requests before they reach
 * their handlers and responses before they are sent to clients.
 */
interface MiddlewareInterface
{
    /**
     * Handle an HTTP request.
     *
     * @param Request $request The incoming request
     * @param Response $response The response object
     * @param callable(Request, Response): void $next The next handler in the pipeline
     */
    public function handle(Request $request, Response $response, callable $next): void;
}
