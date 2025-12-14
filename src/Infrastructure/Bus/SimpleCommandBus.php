<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Bus;

use Luminor\Application\Bus\CommandBusInterface;
use Luminor\Application\Bus\CommandHandlerInterface;
use Luminor\Application\Bus\CommandHandlerNotFoundException;
use Luminor\Application\CQRS\Command;
use Luminor\Application\Validation\CommandValidator;

/**
 * Simple in-memory command bus implementation.
 *
 * Routes commands to their registered handlers and optionally
 * validates commands before execution.
 */
final class SimpleCommandBus implements CommandBusInterface
{
    /** @var array<class-string<Command>, CommandHandlerInterface> */
    private array $handlers = [];

    /** @var array<class-string<Command>, callable(): CommandHandlerInterface> */
    private array $lazyHandlers = [];

    /** @var array<int, MiddlewareInterface> */
    private array $middlewares = [];

    public function __construct(
        private readonly ?CommandValidator $validator = null,
        private readonly ?HandlerResolver $resolver = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function dispatch(Command $command): mixed
    {
        $commandClass = $command::class;

        // Validate command if validator is configured
        $this->validator?->validate($command);

        // Get the handler
        $handler = $this->resolveHandler($commandClass);

        // Execute through middleware pipeline
        $execute = fn(Command $cmd) => $handler->handle($cmd);

        return $this->executeWithMiddleware($command, $execute);
    }

    /**
     * @inheritDoc
     */
    public function register(string $commandClass, CommandHandlerInterface $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * @inheritDoc
     */
    public function registerLazy(string $commandClass, callable $resolver): void
    {
        $this->lazyHandlers[$commandClass] = $resolver;
    }

    /**
     * Register a handler by class name (requires HandlerResolver).
     *
     * @param class-string<Command> $commandClass
     * @param class-string<CommandHandlerInterface> $handlerClass
     */
    public function registerHandler(string $commandClass, string $handlerClass): void
    {
        $this->lazyHandlers[$commandClass] = fn() => $this->resolver?->resolve($handlerClass)
            ?? throw new \RuntimeException('HandlerResolver is required for class-based registration');
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]) || isset($this->lazyHandlers[$commandClass]);
    }

    /**
     * Add middleware to the command bus.
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Resolve the handler for a command.
     *
     * @param class-string<Command> $commandClass
     */
    private function resolveHandler(string $commandClass): CommandHandlerInterface
    {
        // Check for direct handler registration
        if (isset($this->handlers[$commandClass])) {
            return $this->handlers[$commandClass];
        }

        // Check for lazy handler registration
        if (isset($this->lazyHandlers[$commandClass])) {
            $handler = ($this->lazyHandlers[$commandClass])();
            $this->handlers[$commandClass] = $handler;
            unset($this->lazyHandlers[$commandClass]);
            return $handler;
        }

        throw CommandHandlerNotFoundException::forCommand($commandClass);
    }

    /**
     * Execute command through middleware pipeline.
     *
     * @param callable(Command): mixed $finalHandler
     */
    private function executeWithMiddleware(Command $command, callable $finalHandler): mixed
    {
        if (count($this->middlewares) === 0) {
            return $finalHandler($command);
        }

        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, MiddlewareInterface $middleware) => fn(Command $cmd) => $middleware->handle($cmd, $next),
            $finalHandler
        );

        return $pipeline($command);
    }
}
