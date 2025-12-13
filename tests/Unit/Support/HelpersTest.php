<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Support;

use ArrayObject;
use Luminor\DDD\Config\ConfigRepository;
use Luminor\DDD\Kernel;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class HelpersTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        // Create a kernel for testing
        $this->kernel = new Kernel(__DIR__);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        Kernel::setInstance(null);
        $this->kernel = null;
    }

    // ========================================
    // value() tests
    // ========================================

    public function testValueReturnsScalarValue(): void
    {
        $this->assertSame('hello', value('hello'));
        $this->assertSame(42, value(42));
        $this->assertTrue(value(true));
    }

    public function testValueResolvesClosures(): void
    {
        $result = value(fn () => 'resolved');

        $this->assertSame('resolved', $result);
    }

    public function testValuePassesArgumentsToClosures(): void
    {
        $result = value(fn ($a, $b) => $a + $b, 2, 3);

        $this->assertSame(5, $result);
    }

    // ========================================
    // blank() and filled() tests
    // ========================================

    public function testBlankReturnsTrueForEmptyValues(): void
    {
        $this->assertTrue(blank(null));
        $this->assertTrue(blank(''));
        $this->assertTrue(blank('   '));
        $this->assertTrue(blank([]));
    }

    public function testBlankReturnsFalseForFilledValues(): void
    {
        $this->assertFalse(blank('hello'));
        $this->assertFalse(blank(0));
        $this->assertFalse(blank(false));
        $this->assertFalse(blank(['item']));
    }

    public function testBlankHandlesCountable(): void
    {
        $empty = new ArrayObject();
        $filled = new ArrayObject(['item']);

        $this->assertTrue(blank($empty));
        $this->assertFalse(blank($filled));
    }

    public function testFilledIsOppositeOfBlank(): void
    {
        $this->assertTrue(filled('hello'));
        $this->assertTrue(filled(0));
        $this->assertTrue(filled(false));

        $this->assertFalse(filled(null));
        $this->assertFalse(filled(''));
        $this->assertFalse(filled([]));
    }

    // ========================================
    // class_basename() tests
    // ========================================

    public function testClassBasenameFromString(): void
    {
        $this->assertSame('Kernel', class_basename(Kernel::class));
        $this->assertSame('ConfigRepository', class_basename(ConfigRepository::class));
    }

    public function testClassBasenameFromObject(): void
    {
        $this->assertSame('Kernel', class_basename($this->kernel));
    }

    public function testClassBasenameWithNoNamespace(): void
    {
        $this->assertSame('SimpleClass', class_basename('SimpleClass'));
    }

    // ========================================
    // tap() tests
    // ========================================

    public function testTapReturnsOriginalValue(): void
    {
        $object = new stdClass();
        $object->name = 'original';

        $result = tap($object, function ($obj) {
            $obj->name = 'modified';
        });

        $this->assertSame($object, $result);
        $this->assertSame('modified', $result->name);
    }

    public function testTapWithNullCallbackReturnsValue(): void
    {
        $value = 'test';

        $this->assertSame('test', tap($value));
    }

    // ========================================
    // with() tests
    // ========================================

    public function testWithReturnsValueWhenNoCallback(): void
    {
        $this->assertSame('hello', with('hello'));
    }

    public function testWithAppliesCallback(): void
    {
        $result = with('hello', fn ($value) => strtoupper($value));

        $this->assertSame('HELLO', $result);
    }

    // ========================================
    // transform() tests
    // ========================================

    public function testTransformAppliesCallbackWhenFilled(): void
    {
        $result = transform('hello', fn ($value) => strtoupper($value));

        $this->assertSame('HELLO', $result);
    }

    public function testTransformReturnsDefaultWhenBlank(): void
    {
        $result = transform('', fn ($value) => strtoupper($value), 'default');

        $this->assertSame('default', $result);
    }

    public function testTransformCallsDefaultWhenCallable(): void
    {
        $result = transform(null, fn ($value) => $value, fn () => 'callable_default');

        $this->assertSame('callable_default', $result);
    }

    // ========================================
    // throw_if() and throw_unless() tests
    // ========================================

    public function testThrowIfThrowsWhenConditionTrue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Condition was true');

        throw_if(true, RuntimeException::class, 'Condition was true');
    }

    public function testThrowIfReturnsConditionWhenFalse(): void
    {
        $result = throw_if(false, RuntimeException::class, 'Should not throw');

        $this->assertFalse($result);
    }

    public function testThrowUnlessThrowsWhenConditionFalse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Condition was false');

        throw_unless(false, RuntimeException::class, 'Condition was false');
    }

    public function testThrowUnlessReturnsConditionWhenTrue(): void
    {
        $result = throw_unless(true, RuntimeException::class, 'Should not throw');

        $this->assertTrue($result);
    }

    public function testThrowIfWithStringMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom message');

        throw_if(true, 'Custom message');
    }

    // ========================================
    // data_get() tests
    // ========================================

    public function testDataGetFromArray(): void
    {
        $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];

        $this->assertSame('John', data_get($data, 'user.name'));
        $this->assertSame('john@example.com', data_get($data, 'user.email'));
    }

    public function testDataGetFromObject(): void
    {
        $data = (object) ['user' => (object) ['name' => 'John']];

        $this->assertSame('John', data_get($data, 'user.name'));
    }

    public function testDataGetReturnsDefaultWhenNotFound(): void
    {
        $data = ['key' => 'value'];

        $this->assertSame('default', data_get($data, 'missing', 'default'));
    }

    public function testDataGetReturnsTargetWhenKeyIsNull(): void
    {
        $data = ['key' => 'value'];

        $this->assertSame($data, data_get($data, null));
    }

    // ========================================
    // data_set() tests
    // ========================================

    public function testDataSetOnArray(): void
    {
        $data = [];

        data_set($data, 'user.name', 'John');

        $this->assertSame('John', $data['user']['name']);
    }

    public function testDataSetRespectsOverwriteFlag(): void
    {
        $data = ['key' => 'original'];

        data_set($data, 'key', 'new', false);
        $this->assertSame('original', $data['key']);

        data_set($data, 'key', 'new', true);
        $this->assertSame('new', $data['key']);
    }

    // ========================================
    // windows_os() tests
    // ========================================

    public function testWindowsOs(): void
    {
        $expected = PHP_OS_FAMILY === 'Windows';
        $this->assertSame($expected, windows_os());
    }

    // ========================================
    // app() helper tests
    // ========================================

    public function testAppReturnsKernelInstance(): void
    {
        $this->assertSame($this->kernel, app());
    }

    public function testAppResolvesFromContainer(): void
    {
        $result = app(ConfigRepository::class);

        $this->assertInstanceOf(ConfigRepository::class, $result);
    }

    // ========================================
    // config() helper tests
    // ========================================

    public function testConfigGetsValue(): void
    {
        $this->kernel->getConfig()->set('test.key', 'test_value');

        $this->assertSame('test_value', config('test.key'));
    }

    public function testConfigGetsDefaultWhenNotFound(): void
    {
        $this->assertSame('default', config('nonexistent.key', 'default'));
    }

    public function testConfigSetsValues(): void
    {
        config(['new.key' => 'new_value']);

        $this->assertSame('new_value', config('new.key'));
    }

    public function testConfigReturnsRepositoryWhenNoKey(): void
    {
        $this->assertInstanceOf(ConfigRepository::class, config());
    }

    // ========================================
    // Path helper tests
    // ========================================

    public function testBasePathReturnsBasePath(): void
    {
        $this->assertSame(__DIR__, base_path());
    }

    public function testBasePathAppendsPath(): void
    {
        $expected = __DIR__ . DIRECTORY_SEPARATOR . 'config';
        $this->assertSame($expected, base_path('config'));
    }

    public function testConfigPathReturnsConfigPath(): void
    {
        $expected = __DIR__ . '/config';
        $this->assertSame($expected, config_path());
    }

    public function testStoragePathReturnsStoragePath(): void
    {
        $expected = __DIR__ . '/storage';
        $this->assertSame($expected, storage_path());
    }

    // ========================================
    // env() helper tests
    // ========================================

    public function testEnvHelperWorks(): void
    {
        $_ENV['TEST_HELPER_VAR'] = 'helper_value';

        $this->assertSame('helper_value', env('TEST_HELPER_VAR'));
        $this->assertSame('default', env('NONEXISTENT', 'default'));

        unset($_ENV['TEST_HELPER_VAR']);
    }
}
