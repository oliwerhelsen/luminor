<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Domain;

use Luminor\DDD\Domain\Abstractions\DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DomainExceptionTest extends TestCase
{
    public function testExceptionHasMessage(): void
    {
        $exception = new DomainException('Something went wrong');

        $this->assertSame('Something went wrong', $exception->getMessage());
    }

    public function testExceptionHasDefaultErrorCode(): void
    {
        $exception = new DomainException('Something went wrong');

        $this->assertSame('DOMAIN_ERROR', $exception->getErrorCode());
    }

    public function testExceptionHasCustomErrorCode(): void
    {
        $exception = new DomainException('User not found', 'USER_NOT_FOUND');

        $this->assertSame('USER_NOT_FOUND', $exception->getErrorCode());
    }

    public function testExceptionHasEmptyContextByDefault(): void
    {
        $exception = new DomainException('Something went wrong');

        $this->assertSame([], $exception->getContext());
    }

    public function testExceptionHasCustomContext(): void
    {
        $context = ['userId' => 123, 'action' => 'delete'];
        $exception = new DomainException('Operation failed', 'OP_FAILED', $context);

        $this->assertSame($context, $exception->getContext());
    }

    public function testExceptionHasPreviousException(): void
    {
        $previous = new RuntimeException('Original error');
        $exception = new DomainException('Wrapped error', 'WRAPPED', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testToArrayReturnsExceptionData(): void
    {
        $exception = new DomainException(
            'User not found',
            'USER_NOT_FOUND',
            ['userId' => 123],
        );

        $array = $exception->toArray();

        $this->assertSame([
            'message' => 'User not found',
            'errorCode' => 'USER_NOT_FOUND',
            'context' => ['userId' => 123],
        ], $array);
    }

    public function testWithCodeStaticConstructor(): void
    {
        $exception = DomainException::withCode(
            'Invalid email format',
            'INVALID_EMAIL',
            ['email' => 'not-an-email'],
        );

        $this->assertSame('Invalid email format', $exception->getMessage());
        $this->assertSame('INVALID_EMAIL', $exception->getErrorCode());
        $this->assertSame(['email' => 'not-an-email'], $exception->getContext());
    }

    public function testCustomDomainExceptionInheritance(): void
    {
        $exception = UserNotFoundException::withCode(
            'User with ID 123 not found',
            'USER_NOT_FOUND',
            ['userId' => 123],
        );

        $this->assertInstanceOf(UserNotFoundException::class, $exception);
        $this->assertInstanceOf(DomainException::class, $exception);
    }
}

final class UserNotFoundException extends DomainException
{
}
