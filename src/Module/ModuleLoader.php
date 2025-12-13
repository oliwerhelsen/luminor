<?php

declare(strict_types=1);

namespace Luminor\DDD\Module;

use Luminor\DDD\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Loads and manages application modules.
 *
 * The ModuleLoader handles module discovery, dependency resolution,
 * and lifecycle management.
 */
final class ModuleLoader
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, bool> */
    private array $registered = [];

    /** @var array<string, bool> */
    private array $booted = [];

    private bool $isBooted = false;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Register a module.
     *
     * @throws ModuleException If the module is already registered
     */
    public function register(ModuleInterface $module): void
    {
        $name = $module->getName();

        if (isset($this->modules[$name])) {
            throw ModuleException::alreadyRegistered($name);
        }

        $this->modules[$name] = $module;
    }

    /**
     * Register multiple modules.
     *
     * @param array<int, ModuleInterface> $modules
     */
    public function registerAll(array $modules): void
    {
        foreach ($modules as $module) {
            $this->register($module);
        }
    }

    /**
     * Discover and register modules from a directory.
     *
     * Looks for classes implementing ModuleInterface in the given directory.
     *
     * @param string $directory Path to search for modules
     * @param string $namespace Base namespace for the modules
     */
    public function discover(string $directory, string $namespace): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->loadModuleFromFile($file->getPathname(), $directory, $namespace);
            }
        }
    }

    /**
     * Boot all registered modules.
     *
     * This resolves dependencies and calls register() and boot() on each module.
     *
     * @throws ModuleException If dependencies cannot be resolved
     */
    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        // Resolve load order based on dependencies
        $loadOrder = $this->resolveDependencies();

        // Register all modules
        foreach ($loadOrder as $name) {
            $this->registerModule($name);
        }

        // Boot all modules
        foreach ($loadOrder as $name) {
            $this->bootModule($name);
        }

        $this->isBooted = true;
    }

    /**
     * Get a registered module by name.
     */
    public function getModule(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, ModuleInterface>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Check if a module is registered.
     */
    public function hasModule(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Check if a module is enabled.
     */
    public function isModuleEnabled(string $name): bool
    {
        $module = $this->getModule($name);

        return $module !== null && $module->getDefinition()->isEnabled();
    }

    /**
     * Check if a module has been booted.
     */
    public function isModuleBooted(string $name): bool
    {
        return $this->booted[$name] ?? false;
    }

    /**
     * Get the names of all registered modules.
     *
     * @return array<int, string>
     */
    public function getModuleNames(): array
    {
        return array_keys($this->modules);
    }

    /**
     * Load a module from a file.
     */
    private function loadModuleFromFile(string $filePath, string $baseDir, string $baseNamespace): void
    {
        $relativePath = str_replace($baseDir, '', $filePath);
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        $relativePath = preg_replace('/\.php$/', '', $relativePath);

        $className = $baseNamespace . '\\' . $relativePath;

        if (! class_exists($className)) {
            require_once $filePath;
        }

        if (class_exists($className) && $this->implementsModule($className)) {
            $module = new $className();
            $this->register($module);
        }
    }

    /**
     * Check if a class implements ModuleInterface.
     *
     * @param class-string $className
     */
    private function implementsModule(string $className): bool
    {
        $interfaces = class_implements($className);

        return $interfaces !== false && in_array(ModuleInterface::class, $interfaces, true);
    }

    /**
     * Resolve module dependencies and return load order.
     *
     * @return array<int, string>
     *
     * @throws ModuleException If circular dependency detected
     */
    private function resolveDependencies(): array
    {
        $resolved = [];
        $unresolved = [];

        foreach (array_keys($this->modules) as $name) {
            $this->resolveDependency($name, $resolved, $unresolved);
        }

        return $resolved;
    }

    /**
     * Resolve a single module's dependencies.
     *
     * @param array<int, string> $resolved
     * @param array<int, string> $unresolved
     *
     * @throws ModuleException If dependency not found or circular
     */
    private function resolveDependency(string $name, array &$resolved, array &$unresolved): void
    {
        if (in_array($name, $resolved, true)) {
            return;
        }

        if (in_array($name, $unresolved, true)) {
            throw ModuleException::circularDependency($name);
        }

        $unresolved[] = $name;

        $module = $this->modules[$name] ?? throw ModuleException::notFound($name);

        foreach ($module->getDependencies() as $dependency) {
            if (! isset($this->modules[$dependency])) {
                throw ModuleException::dependencyNotFound($name, $dependency);
            }
            $this->resolveDependency($dependency, $resolved, $unresolved);
        }

        $resolved[] = $name;
        $unresolved = array_diff($unresolved, [$name]);
    }

    /**
     * Register a single module.
     */
    private function registerModule(string $name): void
    {
        if ($this->registered[$name] ?? false) {
            return;
        }

        $module = $this->modules[$name];

        if (! $module->getDefinition()->isEnabled()) {
            return;
        }

        $module->register($this->container);
        $this->registered[$name] = true;
    }

    /**
     * Boot a single module.
     */
    private function bootModule(string $name): void
    {
        if ($this->booted[$name] ?? false) {
            return;
        }

        if (! ($this->registered[$name] ?? false)) {
            return;
        }

        $module = $this->modules[$name];
        $module->boot($this->container);
        $this->booted[$name] = true;
    }
}
