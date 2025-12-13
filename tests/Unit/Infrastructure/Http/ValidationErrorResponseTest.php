<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Infrastructure\Http;

use Luminor\DDD\Application\Validation\ValidationResult;
use Luminor\DDD\Infrastructure\Http\Response\ValidationErrorResponse;
use PHPUnit\Framework\TestCase;

final class ValidationErrorResponseTest extends TestCase
{
    public function testCreateWithSingleError(): void
    {
        $response = ValidationErrorResponse::withError('email', 'Invalid email format');

        $this->assertSame('Invalid email format', $response->getMessage());
        $this->assertTrue($response->hasErrorsFor('email'));
        $this->assertSame(['Invalid email format'], $response->getErrorsFor('email'));
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithMultipleErrors(): void
    {
        $response = ValidationErrorResponse::withErrors([
            'email' => 'Invalid email',
            'name' => ['Name is required', 'Name must be at least 2 characters'],
        ]);

        $this->assertTrue($response->hasErrorsFor('email'));
        $this->assertTrue($response->hasErrorsFor('name'));
        $this->assertSame(['Invalid email'], $response->getErrorsFor('email'));
        $this->assertCount(2, $response->getErrorsFor('name'));
    }

    public function testCreateFromValidationResult(): void
    {
        $result = ValidationResult::invalid()
            ->addError('email', 'Invalid email')
            ->addError('password', 'Password too short');

        $response = ValidationErrorResponse::fromValidationResult($result);

        $this->assertTrue($response->hasErrorsFor('email'));
        $this->assertTrue($response->hasErrorsFor('password'));
    }

    public function testToArrayFormat(): void
    {
        $response = ValidationErrorResponse::withErrors([
            'email' => 'Invalid email',
            'name' => 'Name required',
        ]);

        $array = $response->toArray();

        $this->assertFalse($array['success']);
        $this->assertSame(422, $array['statusCode']);
        $this->assertSame('VALIDATION_ERROR', $array['error']['code']);
        $this->assertArrayHasKey('errors', $array['error']);
        $this->assertArrayHasKey('email', $array['error']['errors']);
        $this->assertArrayHasKey('name', $array['error']['errors']);
    }

    public function testHasErrorsForReturnsFalseForNonExistentField(): void
    {
        $response = ValidationErrorResponse::withError('email', 'Invalid email');

        $this->assertFalse($response->hasErrorsFor('name'));
    }

    public function testGetErrorsForReturnsEmptyArrayForNonExistentField(): void
    {
        $response = ValidationErrorResponse::withError('email', 'Invalid email');

        $this->assertSame([], $response->getErrorsFor('name'));
    }

    public function testGetAllErrors(): void
    {
        $response = ValidationErrorResponse::withErrors([
            'email' => 'Invalid email',
            'name' => ['Name required', 'Name too short'],
        ]);

        $errors = $response->getErrors();

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
}
