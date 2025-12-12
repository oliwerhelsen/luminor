<?php

declare(strict_types=1);

namespace Lumina\DDD\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Simple dependency injection container.
 *
 * Provides automatic dependency resolution, singleton support,
 * and method injection.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable|string|null> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    private static ?self $instance = null;

    /**
     * Get the global container instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set the global container instance.
     */
    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    /**
     * @inheritDoc
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        unset($this->instances[$abstract]);
    }

    /**
     * @inheritDoc
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    /**
     * @inheritDoc
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for an abstract type.
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * @inheritDoc
     */
    public function make(string $abstract): object
    {
        return $this->resolve($abstract);
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return $this->bound($id) || isset($this->instances[$id]) || isset($this->aliases[$id]);
    }

    /**
     * @inheritDoc
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || isset($this->aliases[$abstract]);
    }

    /**
     * @inheritDoc
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$target, $method] = $callback;

            if (is_string($target)) {
                $target = $this->make($target);
            }

            $reflection = new ReflectionMethod($target, $method);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);

            return $reflection->invokeArgs($target, $dependencies);
        }

        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);

            return $callback(...$dependencies);
        }

        return $callback(...$parameters);
    }

    /**
     * @inheritDoc
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->singletons = [];
        $this->instances = [];
        $this->aliases = [];
    }

    /**
     * Resolve a type from the container.
     */
    private function resolve(string $abstract): object
    {
        // Resolve aliases
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // Return existing instance if available
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get the concrete implementation
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // Build the instance
        $instance = $this->build($concrete);

        // Store singleton instances
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build an instance of the given type.
     *
     * @param callable|string $concrete
     */
    private function build(callable|string $concrete): object
    {
        // If concrete is a closure, call it
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // If concrete is callable (but not a class string), call it
        if (is_callable($concrete) && !is_string($concrete)) {
            return $concrete($this);
        }

        // Build from class name
        if (!is_string($concrete)) {
            throw new ContainerException(sprintf(
                'Cannot build non-string concrete: %s',
                get_debug_type($concrete)
            ));
        }

        if (!class_exists($concrete)) {
            throw new ContainerException(sprintf(
                'Class %s does not exist.',
                $concrete
            ));
        }

        $reflection = new ReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                'Class %s is not instantiable.',
                $concrete
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor/method dependencies.
     *
     * @param array<ReflectionParameter> $parameters
     * @param array<string, mixed> $primitives
     * @return array<mixed>
     */
    private function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if primitive value was provided
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            $type = $parameter->getType();

            // Handle untyped or non-class parameters
            if ($type === null || !$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                if ($parameter->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }

                throw new ContainerException(sprintf(
                    'Unable to resolve parameter $%s with no type hint or default value.',
                    $name
                ));
            }

            // Resolve class dependency
            $typeName = $type->getName();

            try {
                $dependencies[] = $this->resolve($typeName);
            } catch (ContainerException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw $e;
                }
            }
        }

        return $dependencies;
    }
}
