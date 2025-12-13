<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Domain\Abstractions\ValueObject;

final class ValueObjectTest extends TestCase
{
    public function testValueObjectsWithSameValuesAreEqual(): void
    {
        $vo1 = new Money(100, 'USD');
        $vo2 = new Money(100, 'USD');

        $this->assertTrue($vo1->equals($vo2));
    }

    public function testValueObjectsWithDifferentValuesAreNotEqual(): void
    {
        $vo1 = new Money(100, 'USD');
        $vo2 = new Money(200, 'USD');

        $this->assertFalse($vo1->equals($vo2));
    }

    public function testValueObjectsWithDifferentCurrenciesAreNotEqual(): void
    {
        $vo1 = new Money(100, 'USD');
        $vo2 = new Money(100, 'EUR');

        $this->assertFalse($vo1->equals($vo2));
    }

    public function testValueObjectIsNotEqualToNull(): void
    {
        $vo = new Money(100, 'USD');

        $this->assertFalse($vo->equals(null));
    }

    public function testValueObjectIsEqualToItself(): void
    {
        $vo = new Money(100, 'USD');

        $this->assertTrue($vo->equals($vo));
    }

    public function testValueObjectsOfDifferentTypesAreNotEqual(): void
    {
        $money = new Money(100, 'USD');
        $email = new Email('test@example.com');

        $this->assertFalse($money->equals($email));
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $vo = new Money(100, 'USD');

        $array = $vo->toArray();

        $this->assertSame(['amount' => 100, 'currency' => 'USD'], $array);
    }

    public function testToStringReturnsJsonRepresentation(): void
    {
        $vo = new Money(100, 'USD');

        $string = (string) $vo;

        $this->assertSame('{"amount":100,"currency":"USD"}', $string);
    }

    public function testNestedValueObjectsInToArray(): void
    {
        $vo = new Address(
            '123 Main St',
            new City('New York', 'NY')
        );

        $array = $vo->toArray();

        $this->assertSame([
            'street' => '123 Main St',
            'city' => ['name' => 'New York', 'state' => 'NY'],
        ], $array);
    }
}

final class Money extends ValueObject
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency
    ) {
    }

    protected function getEqualityComponents(): array
    {
        return [$this->amount, $this->currency];
    }
}

final class Email extends ValueObject
{
    public function __construct(
        private readonly string $value
    ) {
    }

    protected function getEqualityComponents(): array
    {
        return [$this->value];
    }
}

final class City extends ValueObject
{
    public function __construct(
        private readonly string $name,
        private readonly string $state
    ) {
    }

    protected function getEqualityComponents(): array
    {
        return [$this->name, $this->state];
    }
}

final class Address extends ValueObject
{
    public function __construct(
        private readonly string $street,
        private readonly City $city
    ) {
    }

    protected function getEqualityComponents(): array
    {
        return [$this->street, $this->city];
    }
}
