<?php

declare(strict_types=1);

namespace Luminor\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Extended container interface for dependency injection.
 *
 * Extends PSR-11 ContainerInterface with binding and resolution methods.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a binding in the container.
     *
     * @param string $abstract The abstract type or interface
     * @param callable|string|null $concrete The concrete implementation
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void;

    /**
     * Register a shared/singleton binding.
     *
     * @param string $abstract The abstract type or interface
     * @param callable|string|null $concrete The concrete implementation
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void;

    /**
     * Register an existing instance in the container.
     *
     * @param string $abstract The abstract type or interface
     * @param object $instance The instance to register
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve a type from the container.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object;

    /**
     * Call a method with dependency injection.
     *
     * @param callable|array{object|class-string, string} $callback
     * @param array<string, mixed> $parameters
     */
    public function call(callable|array $callback, array $parameters = []): mixed;

    /**
     * Check if a binding exists.
     */
    public function bound(string $abstract): bool;

    /**
     * Flush all bindings from the container.
     */
    public function flush(): void;
}
