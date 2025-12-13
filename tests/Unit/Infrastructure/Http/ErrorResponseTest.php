<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Infrastructure\Http;

use Luminor\DDD\Infrastructure\Http\Response\ErrorResponse;
use PHPUnit\Framework\TestCase;

final class ErrorResponseTest extends TestCase
{
    public function testBadRequestError(): void
    {
        $error = ErrorResponse::badRequest('Invalid input');

        $this->assertSame('Invalid input', $error->getMessage());
        $this->assertSame('BAD_REQUEST', $error->getCode());
        $this->assertSame(400, $error->getStatusCode());
    }

    public function testUnauthorizedError(): void
    {
        $error = ErrorResponse::unauthorized();

        $this->assertSame('Authentication required', $error->getMessage());
        $this->assertSame('UNAUTHORIZED', $error->getCode());
        $this->assertSame(401, $error->getStatusCode());
    }

    public function testForbiddenError(): void
    {
        $error = ErrorResponse::forbidden();

        $this->assertSame('Access denied', $error->getMessage());
        $this->assertSame('FORBIDDEN', $error->getCode());
        $this->assertSame(403, $error->getStatusCode());
    }

    public function testNotFoundError(): void
    {
        $error = ErrorResponse::notFound('User');

        $this->assertSame('User not found', $error->getMessage());
        $this->assertSame('NOT_FOUND', $error->getCode());
        $this->assertSame(404, $error->getStatusCode());
    }

    public function testMethodNotAllowedError(): void
    {
        $error = ErrorResponse::methodNotAllowed('PATCH');

        $this->assertSame('Method PATCH not allowed', $error->getMessage());
        $this->assertSame('METHOD_NOT_ALLOWED', $error->getCode());
        $this->assertSame(405, $error->getStatusCode());
    }

    public function testConflictError(): void
    {
        $error = ErrorResponse::conflict('Resource already exists');

        $this->assertSame('Resource already exists', $error->getMessage());
        $this->assertSame('CONFLICT', $error->getCode());
        $this->assertSame(409, $error->getStatusCode());
    }

    public function testUnprocessableEntityError(): void
    {
        $error = ErrorResponse::unprocessableEntity('Invalid data', ['field' => 'error']);

        $this->assertSame('Invalid data', $error->getMessage());
        $this->assertSame('UNPROCESSABLE_ENTITY', $error->getCode());
        $this->assertSame(422, $error->getStatusCode());
        $this->assertSame(['field' => 'error'], $error->getDetails());
    }

    public function testTooManyRequestsError(): void
    {
        $error = ErrorResponse::tooManyRequests('Rate limit exceeded', 60);

        $this->assertSame('Rate limit exceeded', $error->getMessage());
        $this->assertSame('TOO_MANY_REQUESTS', $error->getCode());
        $this->assertSame(429, $error->getStatusCode());
        $this->assertSame(['retryAfter' => 60], $error->getDetails());
    }

    public function testServerError(): void
    {
        $error = ErrorResponse::serverError();

        $this->assertSame('An unexpected error occurred', $error->getMessage());
        $this->assertSame('INTERNAL_ERROR', $error->getCode());
        $this->assertSame(500, $error->getStatusCode());
    }

    public function testToArrayIncludesAllFields(): void
    {
        $error = ErrorResponse::badRequest('Test error', ['field' => 'value']);
        $error = $error->withTraceId('trace-123');
        $array = $error->toArray();

        $this->assertFalse($array['success']);
        $this->assertSame(400, $array['statusCode']);
        $this->assertSame('Test error', $array['error']['message']);
        $this->assertSame('BAD_REQUEST', $array['error']['code']);
        $this->assertSame(['field' => 'value'], $array['error']['details']);
        $this->assertSame('trace-123', $array['traceId']);
    }

    public function testToArrayWithoutOptionalFields(): void
    {
        $error = ErrorResponse::serverError();
        $array = $error->toArray();

        $this->assertArrayNotHasKey('details', $array['error']);
        $this->assertArrayNotHasKey('traceId', $array);
    }

    public function testWithTraceIdCreatesNewInstance(): void
    {
        $error1 = ErrorResponse::serverError();
        $error2 = $error1->withTraceId('trace-123');

        $this->assertNull($error1->getTraceId());
        $this->assertSame('trace-123', $error2->getTraceId());
    }
}
