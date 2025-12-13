<?php

declare(strict_types=1);

namespace Luminor\Tests\Auth;

use Luminor\Auth\RateLimit\ArrayRateLimitStore;
use Luminor\Auth\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $store = new ArrayRateLimitStore();
        $this->limiter = new RateLimiter($store, 5, 60);
    }

    public function testAllowsAttemptsUnderLimit(): void
    {
        $key = 'test-key';

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->limiter->attempt($key));
        }
    }

    public function testBlocksAttemptsOverLimit(): void
    {
        $key = 'test-key-2';

        // Use up all attempts
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt($key);
        }

        // Next attempt should fail
        $this->assertFalse($this->limiter->attempt($key));
    }

    public function testTooManyAttempts(): void
    {
        $key = 'test-key-3';

        $this->assertFalse($this->limiter->tooManyAttempts($key));

        // Max out attempts
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key);
        }

        $this->assertTrue($this->limiter->tooManyAttempts($key));
    }

    public function testAttemptsCount(): void
    {
        $key = 'test-key-4';

        $this->assertEquals(0, $this->limiter->attempts($key));

        $this->limiter->hit($key);
        $this->assertEquals(1, $this->limiter->attempts($key));

        $this->limiter->hit($key);
        $this->assertEquals(2, $this->limiter->attempts($key));
    }

    public function testRemaining(): void
    {
        $key = 'test-key-5';

        $this->assertEquals(5, $this->limiter->remaining($key));

        $this->limiter->hit($key);
        $this->assertEquals(4, $this->limiter->remaining($key));

        $this->limiter->hit($key);
        $this->assertEquals(3, $this->limiter->remaining($key));
    }

    public function testClear(): void
    {
        $key = 'test-key-6';

        $this->limiter->hit($key);
        $this->limiter->hit($key);
        $this->assertEquals(2, $this->limiter->attempts($key));

        $this->limiter->clear($key);
        $this->assertEquals(0, $this->limiter->attempts($key));
    }

    public function testReset(): void
    {
        $key = 'test-key-7';

        // Max out attempts
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key);
        }

        $this->assertTrue($this->limiter->tooManyAttempts($key));

        // Reset
        $this->limiter->reset($key);

        $this->assertFalse($this->limiter->tooManyAttempts($key));
        $this->assertTrue($this->limiter->attempt($key));
    }
}
