<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Console\Commands\MakeRepositoryCommand;

final class MakeRepositoryCommandTest extends TestCase
{
    private MakeRepositoryCommand $command;

    protected function setUp(): void
    {
        $this->command = new MakeRepositoryCommand();
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('make:repository', $this->command->getName());
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

    public function testHasImplementationOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('implementation', $options);
        $this->assertSame('i', $options['implementation']['shortcut'] ?? '');
    }

    public function testHasInMemoryOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('in-memory', $options);
        $this->assertSame('m', $options['in-memory']['shortcut'] ?? '');
    }
}
