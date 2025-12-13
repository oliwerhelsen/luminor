<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Domain;

use Luminor\DDD\Domain\Abstractions\Specification;
use PHPUnit\Framework\TestCase;

final class SpecificationTest extends TestCase
{
    public function testSpecificationIsSatisfied(): void
    {
        $spec = new IsAdult();

        $this->assertTrue($spec->isSatisfiedBy(new Person('John', 25)));
        $this->assertFalse($spec->isSatisfiedBy(new Person('Jane', 17)));
    }

    public function testAndSpecificationRequiresBothToBeSatisfied(): void
    {
        $isAdult = new IsAdult();
        $hasLongName = new HasLongName();

        $spec = $isAdult->and($hasLongName);

        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 25)));
        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 25)));
        $this->assertFalse($spec->isSatisfiedBy(new Person('Alexander', 17)));
        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 17)));
    }

    public function testOrSpecificationRequiresOneToBeSatisfied(): void
    {
        $isAdult = new IsAdult();
        $hasLongName = new HasLongName();

        $spec = $isAdult->or($hasLongName);

        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 25)));
        $this->assertTrue($spec->isSatisfiedBy(new Person('John', 25)));
        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 17)));
        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 17)));
    }

    public function testNotSpecificationNegates(): void
    {
        $isAdult = new IsAdult();

        $spec = $isAdult->not();

        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 25)));
        $this->assertTrue($spec->isSatisfiedBy(new Person('Jane', 17)));
    }

    public function testComplexSpecificationCombination(): void
    {
        $isAdult = new IsAdult();
        $hasLongName = new HasLongName();

        // (isAdult AND hasLongName) OR (NOT isAdult)
        $spec = $isAdult->and($hasLongName)->or($isAdult->not());

        // Adult with long name - satisfies first part
        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 25)));

        // Adult with short name - fails both parts
        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 25)));

        // Minor with any name - satisfies second part (NOT isAdult)
        $this->assertTrue($spec->isSatisfiedBy(new Person('Jane', 17)));
        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 17)));
    }

    public function testChainedSpecifications(): void
    {
        $isAdult = new IsAdult();
        $hasLongName = new HasLongName();
        $isMinor = $isAdult->not();

        $spec = $isAdult->and($hasLongName)->or($isMinor->and($hasLongName->not()));

        // Adult with long name
        $this->assertTrue($spec->isSatisfiedBy(new Person('Alexander', 25)));

        // Minor with short name
        $this->assertTrue($spec->isSatisfiedBy(new Person('John', 17)));

        // Adult with short name
        $this->assertFalse($spec->isSatisfiedBy(new Person('John', 25)));

        // Minor with long name
        $this->assertFalse($spec->isSatisfiedBy(new Person('Alexander', 17)));
    }
}

final class Person
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
    }
}

/**
 * @extends Specification<Person>
 */
final class IsAdult extends Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->age >= 18;
    }
}

/**
 * @extends Specification<Person>
 */
final class HasLongName extends Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return strlen($candidate->name) >= 8;
    }
}
