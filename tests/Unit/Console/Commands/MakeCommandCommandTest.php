<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Console\Commands\MakeCommandCommand;

final class MakeCommandCommandTest extends TestCase
{
    private MakeCommandCommand $command;

    protected function setUp(): void
    {
        $this->command = new MakeCommandCommand();
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('make:command', $this->command->getName());
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

    public function testHasNoHandlerOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('no-handler', $options);
        $this->assertSame('n', $options['no-handler']['shortcut'] ?? '');
    }
}
