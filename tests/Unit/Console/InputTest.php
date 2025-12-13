<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Console;

use Luminor\DDD\Console\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testCanGetArgument(): void
    {
        $input = new Input(['name' => 'TestEntity']);

        $this->assertSame('TestEntity', $input->getArgument('name'));
    }

    public function testReturnsDefaultWhenArgumentNotFound(): void
    {
        $input = new Input([]);

        $this->assertSame('default', $input->getArgument('missing', 'default'));
    }

    public function testReturnsNullWhenArgumentNotFoundAndNoDefault(): void
    {
        $input = new Input([]);

        $this->assertNull($input->getArgument('missing'));
    }

    public function testHasArgumentReturnsTrueWhenExists(): void
    {
        $input = new Input(['name' => 'Test']);

        $this->assertTrue($input->hasArgument('name'));
    }

    public function testHasArgumentReturnsFalseWhenNotExists(): void
    {
        $input = new Input([]);

        $this->assertFalse($input->hasArgument('name'));
    }

    public function testCanGetAllArguments(): void
    {
        $arguments = ['name' => 'Test', 'type' => 'entity'];
        $input = new Input($arguments);

        $this->assertSame($arguments, $input->getArguments());
    }

    public function testCanGetStringOption(): void
    {
        $input = new Input([], ['path' => '/custom/path']);

        $this->assertSame('/custom/path', $input->getOption('path'));
    }

    public function testCanGetBooleanOption(): void
    {
        $input = new Input([], ['force' => true]);

        $this->assertTrue($input->getOption('force'));
    }

    public function testReturnsDefaultWhenOptionNotFound(): void
    {
        $input = new Input([], []);

        $this->assertSame('default', $input->getOption('missing', 'default'));
    }

    public function testHasOptionReturnsTrueWhenExists(): void
    {
        $input = new Input([], ['force' => true]);

        $this->assertTrue($input->hasOption('force'));
    }

    public function testHasOptionReturnsFalseWhenNotExists(): void
    {
        $input = new Input([], []);

        $this->assertFalse($input->hasOption('force'));
    }

    public function testCanGetAllOptions(): void
    {
        $options = ['force' => true, 'path' => '/test'];
        $input = new Input([], $options);

        $this->assertSame($options, $input->getOptions());
    }
}
