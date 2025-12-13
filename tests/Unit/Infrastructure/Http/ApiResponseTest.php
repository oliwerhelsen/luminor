<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Infrastructure\Http\Response\ApiResponse;
use Luminor\DDD\Application\DTO\DataTransferObject;
use Luminor\DDD\Application\DTO\PagedResult;

final class ApiResponseTest extends TestCase
{
    public function testSuccessResponseWithData(): void
    {
        $response = ApiResponse::success(['name' => 'John'], 200);

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['statusCode']);
        $this->assertSame(['name' => 'John'], $response['data']);
    }

    public function testSuccessResponseWithoutData(): void
    {
        $response = ApiResponse::success();

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['statusCode']);
        $this->assertArrayNotHasKey('data', $response);
    }

    public function testCreatedResponse(): void
    {
        $response = ApiResponse::created(['id' => 1]);

        $this->assertTrue($response['success']);
        $this->assertSame(201, $response['statusCode']);
        $this->assertSame(['id' => 1], $response['data']);
    }

    public function testNoContentResponse(): void
    {
        $response = ApiResponse::noContent();

        $this->assertTrue($response['success']);
        $this->assertSame(204, $response['statusCode']);
    }

    public function testPaginatedResponse(): void
    {
        $items = [
            new TestDto(['id' => 1, 'name' => 'Item 1']),
            new TestDto(['id' => 2, 'name' => 'Item 2']),
        ];

        $pagedResult = PagedResult::fromItems($items, 10, 1, 2);
        $response = ApiResponse::paginated($pagedResult);

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['statusCode']);
        $this->assertCount(2, $response['data']);
        $this->assertSame(1, $response['pagination']['page']);
        $this->assertSame(2, $response['pagination']['perPage']);
        $this->assertSame(10, $response['pagination']['totalCount']);
        $this->assertSame(5, $response['pagination']['totalPages']);
        $this->assertTrue($response['pagination']['hasNextPage']);
        $this->assertFalse($response['pagination']['hasPreviousPage']);
    }

    public function testErrorResponse(): void
    {
        $response = ApiResponse::error('Something went wrong', 'ERR_001', 400);

        $this->assertFalse($response['success']);
        $this->assertSame(400, $response['statusCode']);
        $this->assertSame('Something went wrong', $response['error']['message']);
        $this->assertSame('ERR_001', $response['error']['code']);
    }

    public function testNotFoundResponse(): void
    {
        $response = ApiResponse::notFound('User not found');

        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['statusCode']);
        $this->assertSame('User not found', $response['error']['message']);
        $this->assertSame('NOT_FOUND', $response['error']['code']);
    }

    public function testUnauthorizedResponse(): void
    {
        $response = ApiResponse::unauthorized();

        $this->assertFalse($response['success']);
        $this->assertSame(401, $response['statusCode']);
        $this->assertSame('UNAUTHORIZED', $response['error']['code']);
    }

    public function testForbiddenResponse(): void
    {
        $response = ApiResponse::forbidden();

        $this->assertFalse($response['success']);
        $this->assertSame(403, $response['statusCode']);
        $this->assertSame('FORBIDDEN', $response['error']['code']);
    }
}

/**
 * Test DTO for response testing.
 */
final class TestDto extends DataTransferObject
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
}
