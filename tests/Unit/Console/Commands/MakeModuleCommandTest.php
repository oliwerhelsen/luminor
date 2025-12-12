<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Console\Commands\MakeModuleCommand;

final class MakeModuleCommandTest extends TestCase
{
    private MakeModuleCommand $command;

    protected function setUp(): void
    {
        $this->command = new MakeModuleCommand();
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('make:module', $this->command->getName());
    }

    public function testHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testHasNameArgument(): void
    {
        $arguments = $this->command->getArguments();

        $this->assertArrayHasKey('name', $arguments);
        $this->assertTrue($arguments['name']['required'] ?? false);
    }

    public function testHasMinimalOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('minimal', $options);
        $this->assertSame('m', $options['minimal']['shortcut'] ?? '');
    }

    public function testHasPathOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('path', $options);
    }

    public function testHasForceOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('force', $options);
    }
}
