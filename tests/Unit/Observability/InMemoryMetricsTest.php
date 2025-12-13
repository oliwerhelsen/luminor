<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Observability;

use Luminor\DDD\Observability\InMemoryMetrics;
use PHPUnit\Framework\TestCase;

final class InMemoryMetricsTest extends TestCase
{
    private InMemoryMetrics $metrics;

    protected function setUp(): void
    {
        $this->metrics = new InMemoryMetrics();
    }

    public function testIncrement(): void
    {
        $this->metrics->increment('test.counter');
        $this->metrics->increment('test.counter');
        $this->metrics->increment('test.counter', 3);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertEquals(5, $allMetrics['test.counter']['value']);
        $this->assertEquals('counter', $allMetrics['test.counter']['type']);
    }

    public function testDecrement(): void
    {
        $this->metrics->increment('test.counter', 10);
        $this->metrics->decrement('test.counter', 3);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertEquals(7, $allMetrics['test.counter']['value']);
    }

    public function testGauge(): void
    {
        $this->metrics->gauge('test.gauge', 42.5);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertEquals(42.5, $allMetrics['test.gauge']['value']);
        $this->assertEquals('gauge', $allMetrics['test.gauge']['type']);
    }

    public function testHistogram(): void
    {
        $this->metrics->histogram('test.histogram', 10.0);
        $this->metrics->histogram('test.histogram', 20.0);
        $this->metrics->histogram('test.histogram', 30.0);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertCount(3, $allMetrics['test.histogram']['values']);
        $this->assertEquals('histogram', $allMetrics['test.histogram']['type']);
    }

    public function testTiming(): void
    {
        $this->metrics->timing('test.timing', 125.5);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertContains(125.5, $allMetrics['test.timing']['values']);
    }

    public function testTimer(): void
    {
        $stopTimer = $this->metrics->timer('test.timer');

        usleep(10000); // 10ms

        $stopTimer();

        $stats = $this->metrics->getStats();

        $this->assertArrayHasKey('test.timer', $stats);
        $this->assertGreaterThanOrEqual(10, $stats['test.timer']['avg']);
    }

    public function testMetricsWithTags(): void
    {
        $this->metrics->increment('http.requests', 1, ['method' => 'GET', 'status' => '200']);
        $this->metrics->increment('http.requests', 1, ['method' => 'POST', 'status' => '201']);

        $allMetrics = $this->metrics->getMetrics();

        $this->assertCount(2, $allMetrics);
    }

    public function testReset(): void
    {
        $this->metrics->increment('test.counter');
        $this->metrics->gauge('test.gauge', 42);

        $this->assertCount(2, $this->metrics->getMetrics());

        $this->metrics->reset();

        $this->assertCount(0, $this->metrics->getMetrics());
    }

    public function testGetStats(): void
    {
        $this->metrics->histogram('response.time', 100);
        $this->metrics->histogram('response.time', 200);
        $this->metrics->histogram('response.time', 300);
        $this->metrics->histogram('response.time', 400);
        $this->metrics->histogram('response.time', 500);

        $stats = $this->metrics->getStats();

        $this->assertEquals('histogram', $stats['response.time']['type']);
        $this->assertEquals(5, $stats['response.time']['count']);
        $this->assertEquals(100, $stats['response.time']['min']);
        $this->assertEquals(500, $stats['response.time']['max']);
        $this->assertEquals(300, $stats['response.time']['avg']);
    }
}
