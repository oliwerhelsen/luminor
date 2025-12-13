<?php

declare(strict_types=1);

namespace Luminor\DDD\Testing;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Luminor\DDD\Container\Container;
use Luminor\DDD\Container\ContainerInterface;

/**
 * Base test case for framework testing.
 *
 * Provides common testing utilities, container setup, and helpers
 * for testing domain-driven applications.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected ?ContainerInterface $container = null;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->createContainer();
        $this->setUpContainer($this->container);
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        $this->container = null;
        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * Create the container instance.
     */
    protected function createContainer(): ContainerInterface
    {
        $container = new Container();
        Container::setInstance($container);
        return $container;
    }

    /**
     * Set up the container with bindings.
     *
     * Override this method to register test-specific bindings.
     */
    protected function setUpContainer(ContainerInterface $container): void
    {
        // Register testing implementations
        $container->singleton(InMemoryCommandBus::class);
        $container->singleton(InMemoryQueryBus::class);
        $container->singleton(InMemoryEventDispatcher::class);
    }

    /**
     * Get the container instance.
     */
    protected function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container not initialized. Make sure setUp() was called.');
        }

        return $this->container;
    }

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    protected function resolve(string $abstract): object
    {
        return $this->getContainer()->get($abstract);
    }

    /**
     * Bind a mock to the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param T $mock
     */
    protected function mock(string $abstract, object $mock): void
    {
        $this->getContainer()->instance($abstract, $mock);
    }

    /**
     * Get the in-memory command bus.
     */
    protected function getCommandBus(): InMemoryCommandBus
    {
        return $this->resolve(InMemoryCommandBus::class);
    }

    /**
     * Get the in-memory query bus.
     */
    protected function getQueryBus(): InMemoryQueryBus
    {
        return $this->resolve(InMemoryQueryBus::class);
    }

    /**
     * Get the in-memory event dispatcher.
     */
    protected function getEventDispatcher(): InMemoryEventDispatcher
    {
        return $this->resolve(InMemoryEventDispatcher::class);
    }

    /**
     * Assert that a command was dispatched.
     *
     * @param class-string $commandClass
     */
    protected function assertCommandDispatched(string $commandClass): void
    {
        $this->assertTrue(
            $this->getCommandBus()->hasDispatched($commandClass),
            sprintf('Failed asserting that command [%s] was dispatched.', $commandClass)
        );
    }

    /**
     * Assert that a command was not dispatched.
     *
     * @param class-string $commandClass
     */
    protected function assertCommandNotDispatched(string $commandClass): void
    {
        $this->assertFalse(
            $this->getCommandBus()->hasDispatched($commandClass),
            sprintf('Failed asserting that command [%s] was not dispatched.', $commandClass)
        );
    }

    /**
     * Assert that a query was dispatched.
     *
     * @param class-string $queryClass
     */
    protected function assertQueryDispatched(string $queryClass): void
    {
        $this->assertTrue(
            $this->getQueryBus()->hasDispatched($queryClass),
            sprintf('Failed asserting that query [%s] was dispatched.', $queryClass)
        );
    }

    /**
     * Assert that an event was dispatched.
     *
     * @param class-string $eventClass
     */
    protected function assertEventDispatched(string $eventClass): void
    {
        $this->assertTrue(
            $this->getEventDispatcher()->hasDispatched($eventClass),
            sprintf('Failed asserting that event [%s] was dispatched.', $eventClass)
        );
    }

    /**
     * Assert that an event was not dispatched.
     *
     * @param class-string $eventClass
     */
    protected function assertEventNotDispatched(string $eventClass): void
    {
        $this->assertFalse(
            $this->getEventDispatcher()->hasDispatched($eventClass),
            sprintf('Failed asserting that event [%s] was not dispatched.', $eventClass)
        );
    }

    /**
     * Assert that a specific number of events were dispatched.
     */
    protected function assertEventDispatchedTimes(string $eventClass, int $times): void
    {
        $count = $this->getEventDispatcher()->getDispatchCount($eventClass);
        $this->assertSame(
            $times,
            $count,
            sprintf('Expected event [%s] to be dispatched %d times, but was dispatched %d times.', $eventClass, $times, $count)
        );
    }
}
