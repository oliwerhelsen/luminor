<?php

declare(strict_types=1);

namespace Luminor\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Luminor\Console\Application;

final class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testCanSetAndGetName(): void
    {
        $this->app->setName('Test App');

        $this->assertSame('Test App', $this->app->getName());
    }

    public function testCanSetAndGetVersion(): void
    {
        $this->app->setVersion('2.0.0');

        $this->assertSame('2.0.0', $this->app->getVersion());
    }

    public function testHasDefaultName(): void
    {
        $this->assertSame('Luminor DDD Framework', $this->app->getName());
    }

    public function testHasDefaultVersion(): void
    {
        $this->assertSame('1.0.0', $this->app->getVersion());
    }

    public function testDefaultCommandsAreRegistered(): void
    {
        $commands = $this->app->getCommands();

        $this->assertArrayHasKey('make:entity', $commands);
        $this->assertArrayHasKey('make:repository', $commands);
        $this->assertArrayHasKey('make:command', $commands);
        $this->assertArrayHasKey('make:query', $commands);
        $this->assertArrayHasKey('make:controller', $commands);
        $this->assertArrayHasKey('make:module', $commands);
    }

    public function testHasCommandReturnsTrueForExistingCommand(): void
    {
        $this->assertTrue($this->app->hasCommand('make:entity'));
    }

    public function testHasCommandReturnsFalseForNonExistingCommand(): void
    {
        $this->assertFalse($this->app->hasCommand('make:nonexistent'));
    }

    public function testGetCommandReturnsCommandForExistingName(): void
    {
        $command = $this->app->getCommand('make:entity');

        $this->assertNotNull($command);
        $this->assertSame('make:entity', $command->getName());
    }

    public function testGetCommandReturnsNullForNonExistingName(): void
    {
        $command = $this->app->getCommand('make:nonexistent');

        $this->assertNull($command);
    }

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->app->setName('Test');

        $this->assertSame($this->app, $result);
    }

    public function testSetVersionReturnsSelf(): void
    {
        $result = $this->app->setVersion('1.0.0');

        $this->assertSame($this->app, $result);
    }
}
