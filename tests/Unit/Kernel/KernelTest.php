<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Config\ConfigRepository;
use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\Container;
use Luminor\DDD\Container\ContainerInterface;
use Luminor\DDD\Kernel;

final class KernelTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/luminor-ddd-test-' . uniqid();
        mkdir($this->basePath);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        $this->removeDirectory($this->basePath);
    }

    public function testKernelCanBeCreated(): void
    {
        $kernel = new Kernel($this->basePath);

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertSame($this->basePath, $kernel->getBasePath());
    }

    public function testKernelUsesProvidedContainer(): void
    {
        $container = new Container();
        $kernel = new Kernel($this->basePath, $container);

        $this->assertSame($container, $kernel->getContainer());
    }

    public function testKernelBootsSuccessfully(): void
    {
        $kernel = new Kernel($this->basePath);

        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function testKernelBootIsIdempotent(): void
    {
        $kernel = new Kernel($this->basePath);

        $kernel->boot();
        $kernel->boot(); // Should not throw

        $this->assertTrue($kernel->isBooted());
    }

    public function testKernelRegistersBaseBindings(): void
    {
        $kernel = new Kernel($this->basePath);
        $container = $kernel->getContainer();

        $this->assertTrue($container->has(Kernel::class));
        $this->assertTrue($container->has(ContainerInterface::class));
        $this->assertTrue($container->has(ConfigRepository::class));
    }

    public function testKernelLoadsConfiguration(): void
    {
        // Create config directory and file
        mkdir($this->basePath . '/config');
        file_put_contents(
            $this->basePath . '/config/app.php',
            '<?php return ["name" => "Test App", "debug" => true];'
        );

        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertSame('Test App', $kernel->config('app.name'));
        $this->assertTrue($kernel->config('app.debug'));
    }

    public function testRegisterServiceProvider(): void
    {
        $kernel = new Kernel($this->basePath);
        $provider = new TestServiceProvider();

        $kernel->register($provider);

        $this->assertContains($provider, $kernel->getProviders());
    }

    public function testServiceProviderIsRegisteredOnce(): void
    {
        $kernel = new Kernel($this->basePath);
        $provider = new TestServiceProvider();

        $kernel->register($provider);
        $kernel->register($provider);

        $this->assertCount(1, $kernel->getProviders());
    }

    public function testServiceProviderIsBootedAfterKernelBoot(): void
    {
        $kernel = new Kernel($this->basePath);
        $provider = new TestServiceProvider();

        $kernel->register($provider);
        $kernel->boot();

        $this->assertTrue($provider->booted);
    }

    public function testServiceProviderRegisteredAfterBootIsBootedImmediately(): void
    {
        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $provider = new TestServiceProvider();
        $kernel->register($provider);

        $this->assertTrue($provider->booted);
    }

    public function testDeferredServiceProviderIsNotRegisteredImmediately(): void
    {
        $kernel = new Kernel($this->basePath);

        $provider = new DeferredServiceProvider();
        $kernel->register($provider);

        $this->assertNotContains($provider, $kernel->getProviders());
    }

    public function testDeferredServiceProviderIsResolvedOnDemand(): void
    {
        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $provider = new DeferredServiceProvider();
        $kernel->register($provider);

        $kernel->resolveDeferredProvider(DeferredService::class);

        $this->assertContains($provider, $kernel->getProviders());
    }

    public function testGetConfigPath(): void
    {
        $kernel = new Kernel($this->basePath);

        $this->assertSame($this->basePath . '/config', $kernel->getConfigPath());
    }

    public function testGetStoragePath(): void
    {
        $kernel = new Kernel($this->basePath);

        $this->assertSame($this->basePath . '/storage', $kernel->getStoragePath());
    }

    public function testGetStoragePathFromConfig(): void
    {
        mkdir($this->basePath . '/config');
        file_put_contents(
            $this->basePath . '/config/app.php',
            '<?php return ["storage_path" => "/custom/storage"];'
        );

        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertSame('/custom/storage', $kernel->getStoragePath());
    }

    public function testGetEnvironment(): void
    {
        mkdir($this->basePath . '/config');
        file_put_contents(
            $this->basePath . '/config/app.php',
            '<?php return ["env" => "testing"];'
        );

        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertSame('testing', $kernel->getEnvironment());
    }

    public function testGetEnvironmentDefaultsToProduction(): void
    {
        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertSame('production', $kernel->getEnvironment());
    }

    public function testIsDebug(): void
    {
        mkdir($this->basePath . '/config');
        file_put_contents(
            $this->basePath . '/config/app.php',
            '<?php return ["debug" => true];'
        );

        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertTrue($kernel->isDebug());
    }

    public function testIsProduction(): void
    {
        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertTrue($kernel->isProduction());
    }

    public function testIsLocal(): void
    {
        mkdir($this->basePath . '/config');
        file_put_contents(
            $this->basePath . '/config/app.php',
            '<?php return ["env" => "local"];'
        );

        $kernel = new Kernel($this->basePath);
        $kernel->boot();

        $this->assertTrue($kernel->isLocal());
        $this->assertFalse($kernel->isProduction());
    }

    public function testMake(): void
    {
        $kernel = new Kernel($this->basePath);

        $config = $kernel->make(ConfigRepository::class);

        $this->assertInstanceOf(ConfigRepository::class, $config);
    }

    public function testTerminate(): void
    {
        $kernel = new Kernel($this->basePath);
        $provider = new TerminableServiceProvider();

        $kernel->register($provider);
        $kernel->boot();
        $kernel->terminate();

        $this->assertTrue($provider->terminated);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            is_dir($filePath) ? $this->removeDirectory($filePath) : unlink($filePath);
        }

        rmdir($path);
    }
}

class TestServiceProvider extends AbstractServiceProvider
{
    public bool $booted = false;

    public function register(ContainerInterface $container): void
    {
        // Register bindings
    }

    public function boot(ContainerInterface $container): void
    {
        $this->booted = true;
    }
}

class DeferredServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->bind(DeferredService::class, DeferredService::class);
    }

    public function provides(): array
    {
        return [DeferredService::class];
    }

    public function isDeferred(): bool
    {
        return true;
    }
}

class DeferredService
{
}

class TerminableServiceProvider extends AbstractServiceProvider
{
    public bool $terminated = false;

    public function register(ContainerInterface $container): void
    {
    }

    public function terminate(ContainerInterface $container): void
    {
        $this->terminated = true;
    }
}
