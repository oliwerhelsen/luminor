<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Console\Commands\MakeEntityCommand;

final class MakeEntityCommandTest extends TestCase
{
    private MakeEntityCommand $command;

    protected function setUp(): void
    {
        $this->command = new MakeEntityCommand();
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('make:entity', $this->command->getName());
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

    public function testHasAggregateOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('aggregate', $options);
        $this->assertSame('a', $options['aggregate']['shortcut'] ?? '');
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

    public function testHasHelpOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('help', $options);
    }
}
