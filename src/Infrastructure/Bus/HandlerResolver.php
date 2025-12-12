<?php

declare(strict_types=1);

namespace Lumina\DDD\Infrastructure\Bus;

use Psr\Container\ContainerInterface;
use Lumina\DDD\Application\Bus\CommandHandlerInterface;
use Lumina\DDD\Application\Bus\QueryHandlerInterface;

/**
 * Resolves handlers from a dependency injection container or factory.
 *
 * Supports PSR-11 containers and custom factory callbacks.
 */
final class HandlerResolver
{
    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, callable(): object> */
    private array $factories = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null
    ) {
    }

    /**
     * Create a resolver from a PSR-11 container.
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        return new self($container);
    }

    /**
     * Create a resolver with factory callbacks.
     *
     * @param array<class-string, callable(): object> $factories
     */
    public static function fromFactories(array $factories): self
    {
        $resolver = new self();
        foreach ($factories as $class => $factory) {
            $resolver->registerFactory($class, $factory);
        }
        return $resolver;
    }

    /**
     * Resolve a handler by class name.
     *
     * @template T of object
     * @param class-string<T> $handlerClass
     * @return T
     */
    public function resolve(string $handlerClass): object
    {
        // Check for cached instance
        if (isset($this->instances[$handlerClass])) {
            return $this->instances[$handlerClass];
        }

        // Check for registered factory
        if (isset($this->factories[$handlerClass])) {
            $handler = ($this->factories[$handlerClass])();
            $this->instances[$handlerClass] = $handler;
            return $handler;
        }

        // Try container resolution
        if ($this->container !== null && $this->container->has($handlerClass)) {
            $handler = $this->container->get($handlerClass);
            $this->instances[$handlerClass] = $handler;
            return $handler;
        }

        // Fall back to direct instantiation (no dependencies)
        if (class_exists($handlerClass)) {
            $handler = new $handlerClass();
            $this->instances[$handlerClass] = $handler;
            return $handler;
        }

        throw new \RuntimeException(
            sprintf('Unable to resolve handler "%s"', $handlerClass)
        );
    }

    /**
     * Register a factory for a handler class.
     *
     * @param class-string $handlerClass
     * @param callable(): object $factory
     */
    public function registerFactory(string $handlerClass, callable $factory): void
    {
        $this->factories[$handlerClass] = $factory;
    }

    /**
     * Register a pre-built handler instance.
     *
     * @param class-string $handlerClass
     */
    public function registerInstance(string $handlerClass, object $instance): void
    {
        $this->instances[$handlerClass] = $instance;
    }

    /**
     * Check if a handler can be resolved.
     *
     * @param class-string $handlerClass
     */
    public function canResolve(string $handlerClass): bool
    {
        if (isset($this->instances[$handlerClass])) {
            return true;
        }

        if (isset($this->factories[$handlerClass])) {
            return true;
        }

        if ($this->container !== null && $this->container->has($handlerClass)) {
            return true;
        }

        return class_exists($handlerClass);
    }

    /**
     * Clear cached instances (useful for testing).
     */
    public function clearInstances(): void
    {
        $this->instances = [];
    }
}
