<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Config\ConfigRepository;

final class ConfigRepositoryTest extends TestCase
{
    public function testGetReturnsValue(): void
    {
        $config = new ConfigRepository(['key' => 'value']);

        $this->assertSame('value', $config->get('key'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $config = new ConfigRepository();

        $this->assertSame('default', $config->get('nonexistent', 'default'));
    }

    public function testGetWithDotNotation(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
    }

    public function testGetDeeplyNestedValue(): void
    {
        $config = new ConfigRepository([
            'level1' => [
                'level2' => [
                    'level3' => 'deep-value',
                ],
            ],
        ]);

        $this->assertSame('deep-value', $config->get('level1.level2.level3'));
    }

    public function testGetReturnsDefaultForMissingNestedKey(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        $this->assertNull($config->get('database.port'));
        $this->assertSame(5432, $config->get('database.port', 5432));
    }

    public function testSetValue(): void
    {
        $config = new ConfigRepository();

        $config->set('key', 'value');

        $this->assertSame('value', $config->get('key'));
    }

    public function testSetWithDotNotation(): void
    {
        $config = new ConfigRepository();

        $config->set('database.host', 'localhost');
        $config->set('database.port', 3306);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
    }

    public function testSetDeeplyNestedValue(): void
    {
        $config = new ConfigRepository();

        $config->set('level1.level2.level3', 'deep-value');

        $this->assertSame('deep-value', $config->get('level1.level2.level3'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $config = new ConfigRepository(['key' => 'value']);

        $this->assertTrue($config->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $config = new ConfigRepository();

        $this->assertFalse($config->has('key'));
    }

    public function testHasWithDotNotation(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        $this->assertTrue($config->has('database.host'));
        $this->assertFalse($config->has('database.port'));
    }

    public function testAll(): void
    {
        $items = ['key1' => 'value1', 'key2' => 'value2'];
        $config = new ConfigRepository($items);

        $this->assertSame($items, $config->all());
    }

    public function testMerge(): void
    {
        $config = new ConfigRepository(['key1' => 'value1']);

        $config->merge(['key2' => 'value2']);

        $this->assertSame('value1', $config->get('key1'));
        $this->assertSame('value2', $config->get('key2'));
    }

    public function testMergeNestedArrays(): void
    {
        $config = new ConfigRepository([
            'database' => ['host' => 'localhost'],
        ]);

        $config->merge([
            'database' => ['port' => 3306],
        ]);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
    }

    public function testSetMany(): void
    {
        $config = new ConfigRepository();

        $config->setMany([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->assertSame('value1', $config->get('key1'));
        $this->assertSame('value2', $config->get('key2'));
    }

    public function testForget(): void
    {
        $config = new ConfigRepository(['key' => 'value']);

        $config->forget('key');

        $this->assertFalse($config->has('key'));
    }

    public function testForgetWithDotNotation(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $config->forget('database.port');

        $this->assertTrue($config->has('database.host'));
        $this->assertFalse($config->has('database.port'));
    }

    public function testPush(): void
    {
        $config = new ConfigRepository(['items' => ['a', 'b']]);

        $config->push('items', 'c');

        $this->assertSame(['a', 'b', 'c'], $config->get('items'));
    }

    public function testPushCreatesArrayIfNotExists(): void
    {
        $config = new ConfigRepository();

        $config->push('items', 'a');

        $this->assertSame(['a'], $config->get('items'));
    }

    public function testGetSection(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $section = $config->getSection('database');

        $this->assertSame(['host' => 'localhost', 'port' => 3306], $section);
    }

    public function testGetSectionReturnsEmptyArrayForMissingKey(): void
    {
        $config = new ConfigRepository();

        $section = $config->getSection('nonexistent');

        $this->assertSame([], $section);
    }

    public function testGetSectionReturnsEmptyArrayForNonArrayValue(): void
    {
        $config = new ConfigRepository(['key' => 'string-value']);

        $section = $config->getSection('key');

        $this->assertSame([], $section);
    }
}
