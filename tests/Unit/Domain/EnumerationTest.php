<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Lumina\DDD\Domain\Abstractions\Enumeration;

final class EnumerationTest extends TestCase
{
    public function testGetAllReturnsAllEnumerationInstances(): void
    {
        $all = OrderStatus::getAll();

        $this->assertCount(4, $all);
        $this->assertArrayHasKey('PENDING', $all);
        $this->assertArrayHasKey('PROCESSING', $all);
        $this->assertArrayHasKey('SHIPPED', $all);
        $this->assertArrayHasKey('DELIVERED', $all);
    }

    public function testGetValuesReturnsAllValues(): void
    {
        $values = OrderStatus::getValues();

        $this->assertSame([
            'PENDING' => 'pending',
            'PROCESSING' => 'processing',
            'SHIPPED' => 'shipped',
            'DELIVERED' => 'delivered',
        ], $values);
    }

    public function testGetNamesReturnsAllNames(): void
    {
        $names = OrderStatus::getNames();

        $this->assertSame(['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'], $names);
    }

    public function testFromCreatesInstanceFromValue(): void
    {
        $status = OrderStatus::from('pending');

        $this->assertSame('pending', $status->getValue());
        $this->assertSame('PENDING', $status->getName());
    }

    public function testFromThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value "invalid"');

        OrderStatus::from('invalid');
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $status = OrderStatus::tryFrom('invalid');

        $this->assertNull($status);
    }

    public function testTryFromReturnsInstanceForValidValue(): void
    {
        $status = OrderStatus::tryFrom('pending');

        $this->assertNotNull($status);
        $this->assertSame('pending', $status->getValue());
    }

    public function testFromNameCreatesInstanceFromName(): void
    {
        $status = OrderStatus::fromName('PENDING');

        $this->assertSame('pending', $status->getValue());
        $this->assertSame('PENDING', $status->getName());
    }

    public function testFromNameThrowsExceptionForInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid name "INVALID"');

        OrderStatus::fromName('INVALID');
    }

    public function testIsValidReturnsTrueForValidValue(): void
    {
        $this->assertTrue(OrderStatus::isValid('pending'));
        $this->assertTrue(OrderStatus::isValid('processing'));
    }

    public function testIsValidReturnsFalseForInvalidValue(): void
    {
        $this->assertFalse(OrderStatus::isValid('invalid'));
        $this->assertFalse(OrderStatus::isValid('cancelled'));
    }

    public function testEqualsReturnsTrueForSameEnumeration(): void
    {
        $status1 = OrderStatus::from('pending');
        $status2 = OrderStatus::from('pending');

        $this->assertTrue($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForDifferentEnumeration(): void
    {
        $status1 = OrderStatus::from('pending');
        $status2 = OrderStatus::from('processing');

        $this->assertFalse($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForNull(): void
    {
        $status = OrderStatus::from('pending');

        $this->assertFalse($status->equals(null));
    }

    public function testEqualsReturnsFalseForDifferentEnumerationType(): void
    {
        $orderStatus = OrderStatus::from('pending');
        $priority = Priority::from(1);

        $this->assertFalse($orderStatus->equals($priority));
    }

    public function testToStringReturnsValue(): void
    {
        $status = OrderStatus::from('pending');

        $this->assertSame('pending', (string) $status);
    }

    public function testIntegerEnumeration(): void
    {
        $priority = Priority::from(1);

        $this->assertSame(1, $priority->getValue());
        $this->assertSame('LOW', $priority->getName());
        $this->assertSame('1', (string) $priority);
    }
}

/**
 * @extends Enumeration<string>
 */
final class OrderStatus extends Enumeration
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
}

/**
 * @extends Enumeration<int>
 */
final class Priority extends Enumeration
{
    public const LOW = 1;
    public const MEDIUM = 2;
    public const HIGH = 3;
}
