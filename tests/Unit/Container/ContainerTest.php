<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Container;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Container\Container;
use Lumina\DDD\Container\ContainerException;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testBindAndMake(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $instance = $this->container->make(SimpleInterface::class);

        $this->assertInstanceOf(SimpleImplementation::class, $instance);
    }

    public function testBindReturnsNewInstanceEachTime(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $instance1 = $this->container->make(SimpleInterface::class);
        $instance2 = $this->container->make(SimpleInterface::class);

        $this->assertNotSame($instance1, $instance2);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(SimpleInterface::class, SimpleImplementation::class);

        $instance1 = $this->container->make(SimpleInterface::class);
        $instance2 = $this->container->make(SimpleInterface::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testInstanceRegistration(): void
    {
        $instance = new SimpleImplementation();
        $this->container->instance(SimpleInterface::class, $instance);

        $resolved = $this->container->make(SimpleInterface::class);

        $this->assertSame($instance, $resolved);
    }

    public function testBindWithClosure(): void
    {
        $this->container->bind(SimpleInterface::class, function (Container $container) {
            return new SimpleImplementation();
        });

        $instance = $this->container->make(SimpleInterface::class);

        $this->assertInstanceOf(SimpleImplementation::class, $instance);
    }

    public function testAutoResolveDependencies(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $instance = $this->container->make(DependentClass::class);

        $this->assertInstanceOf(DependentClass::class, $instance);
        $this->assertInstanceOf(SimpleImplementation::class, $instance->dependency);
    }

    public function testHasReturnsTrueForBoundAbstract(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $this->assertTrue($this->container->has(SimpleInterface::class));
    }

    public function testHasReturnsFalseForUnboundAbstract(): void
    {
        $this->assertFalse($this->container->has('NonExistent'));
    }

    public function testBoundReturnsTrueForBoundAbstract(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $this->assertTrue($this->container->bound(SimpleInterface::class));
    }

    public function testFlushRemovesAllBindings(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);
        $this->container->singleton('singleton', SimpleImplementation::class);

        $this->container->flush();

        $this->assertFalse($this->container->bound(SimpleInterface::class));
        $this->assertFalse($this->container->bound('singleton'));
    }

    public function testAlias(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);
        $this->container->alias(SimpleInterface::class, 'simple');

        $instance = $this->container->make('simple');

        $this->assertInstanceOf(SimpleImplementation::class, $instance);
    }

    public function testCallWithClosure(): void
    {
        $result = $this->container->call(function () {
            return 'hello';
        });

        $this->assertSame('hello', $result);
    }

    public function testCallWithMethodArray(): void
    {
        $obj = new CallableClass();

        $result = $this->container->call([$obj, 'getValue']);

        $this->assertSame('test-value', $result);
    }

    public function testCallInjectsDependencies(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImplementation::class);

        $result = $this->container->call(function (SimpleInterface $dep) {
            return $dep;
        });

        $this->assertInstanceOf(SimpleImplementation::class, $result);
    }

    public function testCallWithProvidedParameters(): void
    {
        $result = $this->container->call(function ($name) {
            return 'Hello, ' . $name;
        }, ['name' => 'World']);

        $this->assertSame('Hello, World', $result);
    }

    public function testGetInstanceReturnsGlobalContainer(): void
    {
        Container::setInstance($this->container);

        $this->assertSame($this->container, Container::getInstance());
    }

    public function testGetInstanceCreatesNewIfNotSet(): void
    {
        Container::setInstance(null);

        $instance = Container::getInstance();

        $this->assertInstanceOf(Container::class, $instance);
    }

    public function testMakeThrowsForNonExistentClass(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->make('NonExistentClass');
    }

    public function testMakeThrowsForNonInstantiableClass(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->make(SimpleInterface::class);
    }

    public function testMakeWithDefaultParameterValues(): void
    {
        $instance = $this->container->make(ClassWithDefaultParams::class);

        $this->assertInstanceOf(ClassWithDefaultParams::class, $instance);
        $this->assertSame('default', $instance->value);
    }

    public function testMakeWithNullableParameter(): void
    {
        $instance = $this->container->make(ClassWithNullableParam::class);

        $this->assertInstanceOf(ClassWithNullableParam::class, $instance);
        $this->assertNull($instance->dependency);
    }
}

interface SimpleInterface
{
}

class SimpleImplementation implements SimpleInterface
{
}

class DependentClass
{
    public function __construct(public SimpleInterface $dependency)
    {
    }
}

class CallableClass
{
    public function getValue(): string
    {
        return 'test-value';
    }
}

class ClassWithDefaultParams
{
    public function __construct(public string $value = 'default')
    {
    }
}

class ClassWithNullableParam
{
    public function __construct(public ?SimpleInterface $dependency = null)
    {
    }
}
