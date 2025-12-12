<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Domain\Abstractions\Entity;

final class EntityTest extends TestCase
{
    public function testEntityCanBeCreatedWithId(): void
    {
        $entity = new ConcreteEntity('test-id');

        $this->assertSame('test-id', $entity->getId());
    }

    public function testEntityIsNotTransientWhenIdIsSet(): void
    {
        $entity = new ConcreteEntity('test-id');

        $this->assertFalse($entity->isTransient());
    }

    public function testEntityIsTransientWhenIdIsNull(): void
    {
        $entity = new ConcreteEntity(null);

        $this->assertTrue($entity->isTransient());
    }

    public function testEntitiesWithSameIdAreEqual(): void
    {
        $entity1 = new ConcreteEntity('test-id');
        $entity2 = new ConcreteEntity('test-id');

        $this->assertTrue($entity1->equals($entity2));
    }

    public function testEntitiesWithDifferentIdsAreNotEqual(): void
    {
        $entity1 = new ConcreteEntity('id-1');
        $entity2 = new ConcreteEntity('id-2');

        $this->assertFalse($entity1->equals($entity2));
    }

    public function testEntityIsNotEqualToNull(): void
    {
        $entity = new ConcreteEntity('test-id');

        $this->assertFalse($entity->equals(null));
    }

    public function testEntityIsEqualToItself(): void
    {
        $entity = new ConcreteEntity('test-id');

        $this->assertTrue($entity->equals($entity));
    }

    public function testEntitiesOfDifferentTypesAreNotEqual(): void
    {
        $entity1 = new ConcreteEntity('test-id');
        $entity2 = new AnotherConcreteEntity('test-id');

        $this->assertFalse($entity1->equals($entity2));
    }
}

/**
 * @extends Entity<string|null>
 */
final class ConcreteEntity extends Entity
{
    public function __construct(?string $id)
    {
        parent::__construct($id);
    }
}

/**
 * @extends Entity<string>
 */
final class AnotherConcreteEntity extends Entity
{
    public function __construct(string $id)
    {
        parent::__construct($id);
    }
}
