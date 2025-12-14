<?php

declare(strict_types=1);

namespace Luminor\Http\Middleware;

use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Interface for HTTP middleware.
 *
 * Middleware can inspect and modify requests and responses,
 * and control whether the next handler in the pipeline is called.
 */
interface MiddlewareInterface
{
    /**
     * Handle the request.
     *
     * @param Request $request The incoming request
     * @param Response $response The response to populate
     * @param callable(Request, Response): void $next The next handler in the pipeline
     */
    public function handle(Request $request, Response $response, callable $next): void;
}
