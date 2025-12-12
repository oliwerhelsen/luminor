<?php

declare(strict_types=1);

namespace Lumina\DDD;

use Lumina\DDD\Config\ConfigLoader;
use Lumina\DDD\Config\ConfigRepository;
use Lumina\DDD\Container\Container;
use Lumina\DDD\Container\ContainerInterface;
use Lumina\DDD\Container\ServiceProviderInterface;
use Lumina\DDD\Module\ModuleInterface;
use Lumina\DDD\Module\ModuleLoader;
use Lumina\DDD\Support\Env;
use Utopia\Http\Http;

/**
 * Application kernel for bootstrapping the DDD framework.
 *
 * The kernel is responsible for:
 * - Loading configuration
 * - Registering service providers
 * - Loading modules
 * - Bootstrapping the HTTP application
 */
class Kernel
{
    /**
     * The globally available kernel instance.
     */
    protected static ?Kernel $instance = null;

    protected ContainerInterface $container;
    protected ConfigRepository $config;
    protected bool $booted = false;

    /** @var array<ServiceProviderInterface> */
    protected array $providers = [];

    /** @var array<ServiceProviderInterface> */
    protected array $bootedProviders = [];

    /** @var array<ModuleInterface> */
    protected array $modules = [];

    /** @var array<string, ServiceProviderInterface> */
    protected array $deferredProviders = [];

    /**
     * @param string $basePath The application base path
     * @param ContainerInterface|null $container Optional custom container
     */
    public function __construct(
        protected readonly string $basePath,
        ?ContainerInterface $container = null
    ) {
        $this->container = $container ?? new Container();
        $this->config = new ConfigRepository();

        $this->registerBaseBindings();

        // Store instance for global access via helpers
        static::$instance = $this;
    }

    /**
     * Get the globally available kernel instance.
     */
    public static function getInstance(): ?Kernel
    {
        return static::$instance;
    }

    /**
     * Set the globally available kernel instance.
     */
    public static function setInstance(?Kernel $kernel): void
    {
        static::$instance = $kernel;
    }

    /**
     * Bootstrap the application.
     *
     * @return $this
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->registerConfiguredProviders();
        $this->bootProviders();
        $this->loadModules();

        $this->booted = true;

        return $this;
    }

    /**
     * Load environment variables from .env file if vlucas/phpdotenv is available.
     */
    protected function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';

        // Only load if phpdotenv is available and .env exists
        if (!class_exists(\Dotenv\Dotenv::class) || !file_exists($envFile)) {
            return;
        }

        $repository = \Dotenv\Repository\RepositoryBuilder::createWithDefaultAdapters()
            ->immutable()
            ->make();

        // Set the repository on our Env class for consistency
        Env::setRepository($repository);

        $dotenv = \Dotenv\Dotenv::create($repository, $this->basePath);
        $dotenv->safeLoad();
    }

    /**
     * Register base bindings in the container.
     */
    protected function registerBaseBindings(): void
    {
        $this->container->instance(Kernel::class, $this);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(ConfigRepository::class, $this->config);

        if ($this->container instanceof Container) {
            Container::setInstance($this->container);
        }
    }

    /**
     * Load configuration files.
     */
    protected function loadConfiguration(): void
    {
        $configPath = $this->getConfigPath();

        if (is_dir($configPath)) {
            $loader = new ConfigLoader($configPath);
            $this->config = $loader->loadInto();
            $this->container->instance(ConfigRepository::class, $this->config);
        }
    }

    /**
     * Register providers configured in the config file.
     */
    protected function registerConfiguredProviders(): void
    {
        $providers = $this->config->get('app.providers', []);

        if (!is_array($providers)) {
            return;
        }

        foreach ($providers as $providerClass) {
            $this->register($providerClass);
        }
    }

    /**
     * Register a service provider.
     *
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     * @return ServiceProviderInterface
     */
    public function register(ServiceProviderInterface|string $provider): ServiceProviderInterface
    {
        if (is_string($provider)) {
            $provider = $this->container->make($provider);
        }

        // Check if already registered
        foreach ($this->providers as $registered) {
            if ($registered === $provider || get_class($registered) === get_class($provider)) {
                return $registered;
            }
        }

        // Handle deferred providers
        if ($provider->isDeferred()) {
            foreach ($provider->provides() as $service) {
                $this->deferredProviders[$service] = $provider;
            }
            return $provider;
        }

        $provider->register($this->container);
        $this->providers[] = $provider;

        // Boot immediately if kernel is already booted
        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Boot all registered providers.
     */
    protected function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot a single provider.
     */
    protected function bootProvider(ServiceProviderInterface $provider): void
    {
        // Check if already booted
        foreach ($this->bootedProviders as $booted) {
            if ($booted === $provider) {
                return;
            }
        }

        $provider->boot($this->container);
        $this->bootedProviders[] = $provider;
    }

    /**
     * Load modules from the configured module path.
     */
    protected function loadModules(): void
    {
        $modulePath = $this->config->get('app.module_path', $this->basePath . '/src/Modules');

        if (!is_dir($modulePath)) {
            return;
        }

        $loader = new ModuleLoader($modulePath);
        $this->modules = $loader->loadAll();

        foreach ($this->modules as $module) {
            $module->register($this->container);
        }

        foreach ($this->modules as $module) {
            $module->boot($this->container);
        }
    }

    /**
     * Resolve a deferred provider if needed.
     */
    public function resolveDeferredProvider(string $service): void
    {
        if (!isset($this->deferredProviders[$service])) {
            return;
        }

        $provider = $this->deferredProviders[$service];

        // Register the provider
        $provider->register($this->container);
        $this->providers[] = $provider;

        // Boot if kernel is booted
        if ($this->booted) {
            $this->bootProvider($provider);
        }

        // Remove from deferred
        foreach ($provider->provides() as $provided) {
            unset($this->deferredProviders[$provided]);
        }
    }

    /**
     * Get the application container.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the configuration repository.
     */
    public function getConfig(): ConfigRepository
    {
        return $this->config;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $default Default value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /**
     * Get the application base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the configuration path.
     */
    public function getConfigPath(): string
    {
        return $this->basePath . '/config';
    }

    /**
     * Get the storage path.
     */
    public function getStoragePath(): string
    {
        return $this->config->get('app.storage_path', $this->basePath . '/storage');
    }

    /**
     * Check if the kernel has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get all registered providers.
     *
     * @return array<ServiceProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get all loaded modules.
     *
     * @return array<ModuleInterface>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Make a service from the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object
    {
        // Check for deferred providers
        $this->resolveDeferredProvider($abstract);

        return $this->container->make($abstract);
    }

    /**
     * Terminate the application.
     *
     * Called at the end of the request lifecycle.
     */
    public function terminate(): void
    {
        // Allow providers to clean up
        foreach ($this->bootedProviders as $provider) {
            if (method_exists($provider, 'terminate')) {
                $provider->terminate($this->container);
            }
        }

        // Allow modules to clean up
        foreach ($this->modules as $module) {
            if (method_exists($module, 'terminate')) {
                $module->terminate($this->container);
            }
        }
    }

    /**
     * Get the environment name.
     */
    public function getEnvironment(): string
    {
        return $this->config->get('app.env', 'production');
    }

    /**
     * Check if running in debug mode.
     */
    public function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    /**
     * Check if running in production.
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'production';
    }

    /**
     * Check if running in local/development mode.
     */
    public function isLocal(): bool
    {
        return in_array($this->getEnvironment(), ['local', 'development'], true);
    }
}
