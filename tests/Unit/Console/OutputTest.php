<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Console\Output;

final class OutputTest extends TestCase
{
    private Output $output;

    protected function setUp(): void
    {
        $this->output = new Output();
        // Disable ANSI for testing
        $this->output->setAnsi(false);
    }

    public function testWritelnOutputsMessage(): void
    {
        $this->expectOutputString("Hello World\n");

        $this->output->writeln('Hello World');
    }

    public function testWriteOutputsMessageWithoutNewline(): void
    {
        $this->expectOutputString('Hello World');

        $this->output->write('Hello World');
    }

    public function testNewLineOutputsEmptyLines(): void
    {
        $this->expectOutputString("\n\n\n");

        $this->output->newLine(3);
    }

    public function testInfoStripsTagsWhenAnsiDisabled(): void
    {
        $this->expectOutputString("Test message\n");

        $this->output->info('Test message');
    }

    public function testCommentStripsTagsWhenAnsiDisabled(): void
    {
        $this->expectOutputString("Comment\n");

        $this->output->comment('Comment');
    }

    public function testWarningOutputsWithPrefix(): void
    {
        $this->expectOutputString("Warning: Something went wrong\n");

        $this->output->warning('Something went wrong');
    }

    public function testErrorOutputsWithPrefix(): void
    {
        $this->expectOutputString("Error: Something failed\n");

        $this->output->error('Something failed');
    }

    public function testSuccessOutputsWithCheckmark(): void
    {
        $this->expectOutputString("✓ Operation completed\n");

        $this->output->success('Operation completed');
    }

    public function testSetAnsiReturnsInstance(): void
    {
        $result = $this->output->setAnsi(true);

        $this->assertSame($this->output, $result);
    }
}
