<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy\Strategy;

use Luminor\DDD\Multitenancy\Strategy\HeaderStrategy;
use PHPUnit\Framework\TestCase;
use Utopia\Http\Request;

final class HeaderStrategyTest extends TestCase
{
    public function testResolveFromDefaultHeader(): void
    {
        $strategy = new HeaderStrategy();

        $request = $this->createMock(Request::class);
        $request->method('getHeader')
            ->with('X-Tenant-ID')
            ->willReturn('acme-tenant');

        $this->assertSame('acme-tenant', $strategy->resolve($request));
    }

    public function testResolveFromCustomHeader(): void
    {
        $strategy = new HeaderStrategy('X-Organization-ID');

        $request = $this->createMock(Request::class);
        $request->method('getHeader')
            ->with('X-Organization-ID')
            ->willReturn('org-123');

        $this->assertSame('org-123', $strategy->resolve($request));
    }

    public function testReturnsNullWhenHeaderNotPresent(): void
    {
        $strategy = new HeaderStrategy();

        $request = $this->createMock(Request::class);
        $request->method('getHeader')
            ->with('X-Tenant-ID')
            ->willReturn('');

        $this->assertNull($strategy->resolve($request));
    }

    public function testTrimsHeaderValue(): void
    {
        $strategy = new HeaderStrategy();

        $request = $this->createMock(Request::class);
        $request->method('getHeader')
            ->with('X-Tenant-ID')
            ->willReturn('  tenant-with-spaces  ');

        $this->assertSame('tenant-with-spaces', $strategy->resolve($request));
    }

    public function testGetStrategyNameReturnsHeader(): void
    {
        $strategy = new HeaderStrategy();

        $this->assertSame('header', $strategy->getStrategyName());
    }

    public function testGetHeaderNameReturnsConfiguredHeader(): void
    {
        $strategy = new HeaderStrategy('Custom-Tenant');

        $this->assertSame('Custom-Tenant', $strategy->getHeaderName());
    }
}
