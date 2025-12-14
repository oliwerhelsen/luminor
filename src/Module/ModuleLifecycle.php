<?php

declare(strict_types=1);

namespace Luminor\Module;

/**
 * Module lifecycle event types.
 */
enum ModuleLifecycleEvent: string
{
    case BEFORE_REGISTER = 'before_register';
    case AFTER_REGISTER = 'after_register';
    case BEFORE_BOOT = 'before_boot';
    case AFTER_BOOT = 'after_boot';
    case BEFORE_SHUTDOWN = 'before_shutdown';
    case AFTER_SHUTDOWN = 'after_shutdown';
}

/**
 * Manages module lifecycle hooks.
 *
 * Allows registering callbacks for various stages of the module lifecycle.
 */
final class ModuleLifecycle
{
    /** @var array<string, array<int, callable>> */
    private array $hooks = [];

    /**
     * Register a callback for a lifecycle event.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function on(ModuleLifecycleEvent $event, callable $callback): void
    {
        $key = $event->value;

        if (!isset($this->hooks[$key])) {
            $this->hooks[$key] = [];
        }

        $this->hooks[$key][] = $callback;
    }

    /**
     * Register a callback for before module registration.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function beforeRegister(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::BEFORE_REGISTER, $callback);
    }

    /**
     * Register a callback for after module registration.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function afterRegister(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::AFTER_REGISTER, $callback);
    }

    /**
     * Register a callback for before module boot.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function beforeBoot(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::BEFORE_BOOT, $callback);
    }

    /**
     * Register a callback for after module boot.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function afterBoot(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::AFTER_BOOT, $callback);
    }

    /**
     * Register a callback for before module shutdown.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function beforeShutdown(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::BEFORE_SHUTDOWN, $callback);
    }

    /**
     * Register a callback for after module shutdown.
     *
     * @param callable(ModuleInterface): void $callback
     */
    public function afterShutdown(callable $callback): void
    {
        $this->on(ModuleLifecycleEvent::AFTER_SHUTDOWN, $callback);
    }

    /**
     * Trigger callbacks for a lifecycle event.
     */
    public function trigger(ModuleLifecycleEvent $event, ModuleInterface $module): void
    {
        $key = $event->value;

        if (!isset($this->hooks[$key])) {
            return;
        }

        foreach ($this->hooks[$key] as $callback) {
            $callback($module);
        }
    }

    /**
     * Check if any hooks are registered for an event.
     */
    public function hasHooks(ModuleLifecycleEvent $event): bool
    {
        $key = $event->value;
        return isset($this->hooks[$key]) && count($this->hooks[$key]) > 0;
    }

    /**
     * Clear all hooks for an event.
     */
    public function clearHooks(ModuleLifecycleEvent $event): void
    {
        $key = $event->value;
        unset($this->hooks[$key]);
    }

    /**
     * Clear all registered hooks.
     */
    public function clearAll(): void
    {
        $this->hooks = [];
    }
}
