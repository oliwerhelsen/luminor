<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Console\Commands\MakeControllerCommand;

final class MakeControllerCommandTest extends TestCase
{
    private MakeControllerCommand $command;

    protected function setUp(): void
    {
        $this->command = new MakeControllerCommand();
    }

    public function testHasCorrectName(): void
    {
        $this->assertSame('make:controller', $this->command->getName());
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

    public function testHasCrudOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('crud', $options);
        $this->assertSame('c', $options['crud']['shortcut'] ?? '');
    }

    public function testHasResourceOption(): void
    {
        $options = $this->command->getOptions();

        $this->assertArrayHasKey('resource', $options);
        $this->assertSame('r', $options['resource']['shortcut'] ?? '');
    }
}
