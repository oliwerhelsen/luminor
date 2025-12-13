<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Support;

use Luminor\DDD\Support\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    private array $originalEnv;

    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        $_SERVER = $this->originalServer;
        Env::setRepository(null);
    }

    public function testGetReturnsValueFromEnv(): void
    {
        $_ENV['TEST_VAR'] = 'test_value';

        $this->assertSame('test_value', Env::get('TEST_VAR'));
    }

    public function testGetReturnsValueFromServer(): void
    {
        unset($_ENV['TEST_SERVER_VAR']);
        $_SERVER['TEST_SERVER_VAR'] = 'server_value';

        $this->assertSame('server_value', Env::get('TEST_SERVER_VAR'));
    }

    public function testGetReturnsDefaultWhenNotFound(): void
    {
        $this->assertSame('default', Env::get('NONEXISTENT_VAR', 'default'));
    }

    public function testGetReturnsNullWhenNotFoundAndNoDefault(): void
    {
        $this->assertNull(Env::get('NONEXISTENT_VAR'));
    }

    public function testGetResolvesCallableDefault(): void
    {
        $result = Env::get('NONEXISTENT_VAR', fn () => 'callable_default');

        $this->assertSame('callable_default', $result);
    }

    public function testTransformsTrueString(): void
    {
        $_ENV['BOOL_TRUE'] = 'true';
        $_ENV['BOOL_TRUE_PAREN'] = '(true)';

        $this->assertTrue(Env::get('BOOL_TRUE'));
        $this->assertTrue(Env::get('BOOL_TRUE_PAREN'));
    }

    public function testTransformsFalseString(): void
    {
        $_ENV['BOOL_FALSE'] = 'false';
        $_ENV['BOOL_FALSE_PAREN'] = '(false)';

        $this->assertFalse(Env::get('BOOL_FALSE'));
        $this->assertFalse(Env::get('BOOL_FALSE_PAREN'));
    }

    public function testTransformsNullString(): void
    {
        $_ENV['NULL_VAR'] = 'null';
        $_ENV['NULL_VAR_PAREN'] = '(null)';

        $this->assertNull(Env::get('NULL_VAR'));
        $this->assertNull(Env::get('NULL_VAR_PAREN'));
    }

    public function testTransformsEmptyString(): void
    {
        $_ENV['EMPTY_VAR'] = 'empty';
        $_ENV['EMPTY_VAR_PAREN'] = '(empty)';

        $this->assertSame('', Env::get('EMPTY_VAR'));
        $this->assertSame('', Env::get('EMPTY_VAR_PAREN'));
    }

    public function testStripsDoubleQuotes(): void
    {
        $_ENV['QUOTED_VAR'] = '"quoted value"';

        $this->assertSame('quoted value', Env::get('QUOTED_VAR'));
    }

    public function testStripsSingleQuotes(): void
    {
        $_ENV['QUOTED_VAR'] = "'quoted value'";

        $this->assertSame('quoted value', Env::get('QUOTED_VAR'));
    }

    public function testDoesNotStripMixedQuotes(): void
    {
        $_ENV['MIXED_QUOTES'] = '"mixed\'';

        $this->assertSame('"mixed\'', Env::get('MIXED_QUOTES'));
    }

    public function testBooleanTransformIsCaseInsensitive(): void
    {
        $_ENV['BOOL_UPPER'] = 'TRUE';
        $_ENV['BOOL_MIXED'] = 'TrUe';

        $this->assertTrue(Env::get('BOOL_UPPER'));
        $this->assertTrue(Env::get('BOOL_MIXED'));
    }

    public function testGetOrFailReturnsValue(): void
    {
        $_ENV['REQUIRED_VAR'] = 'required_value';

        $this->assertSame('required_value', Env::getOrFail('REQUIRED_VAR'));
    }

    public function testGetOrFailThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment variable [MISSING_REQUIRED] is not set.');

        Env::getOrFail('MISSING_REQUIRED');
    }

    public function testSetAndGetRepository(): void
    {
        $repository = new class () {
            public function get(string $key): ?string
            {
                return $key === 'REPO_VAR' ? 'repo_value' : null;
            }
        };

        Env::setRepository($repository);

        $this->assertSame($repository, Env::getRepository());
        $this->assertSame('repo_value', Env::get('REPO_VAR'));
    }

    public function testEnvTakesPriorityOverServer(): void
    {
        $_ENV['PRIORITY_VAR'] = 'env_value';
        $_SERVER['PRIORITY_VAR'] = 'server_value';

        $this->assertSame('env_value', Env::get('PRIORITY_VAR'));
    }

    public function testPutenvConfiguration(): void
    {
        $this->assertTrue(Env::putenvEnabled());

        Env::enablePutenv(false);
        $this->assertFalse(Env::putenvEnabled());

        Env::enablePutenv(true);
        $this->assertTrue(Env::putenvEnabled());
    }

    public function testPreservesNumericStrings(): void
    {
        $_ENV['NUMERIC_VAR'] = '12345';

        $this->assertSame('12345', Env::get('NUMERIC_VAR'));
    }

    public function testPreservesRegularStrings(): void
    {
        $_ENV['STRING_VAR'] = 'hello world';

        $this->assertSame('hello world', Env::get('STRING_VAR'));
    }
}
