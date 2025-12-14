<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy\Strategy;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Multitenancy\Strategy\PathStrategy;
use Luminor\DDD\Http\Request;

final class PathStrategyTest extends TestCase
{
    public function testResolveFromFirstPathSegment(): void
    {
        $strategy = new PathStrategy(0);

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/acme/api/users');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testResolveFromSecondPathSegment(): void
    {
        $strategy = new PathStrategy(1);

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/api/acme/users');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testResolveWithPrefix(): void
    {
        $strategy = new PathStrategy(0, 'tenants');

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/tenants/acme/api/users');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testReturnsNullWhenPrefixNotFound(): void
    {
        $strategy = new PathStrategy(0, 'tenants');

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/api/acme/users');

        $this->assertNull($strategy->resolve($request));
    }

    public function testReturnsNullForEmptyPath(): void
    {
        $strategy = new PathStrategy(0);

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('');

        $this->assertNull($strategy->resolve($request));
    }

    public function testReturnsNullWhenPositionOutOfBounds(): void
    {
        $strategy = new PathStrategy(5);

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/acme/api');

        $this->assertNull($strategy->resolve($request));
    }

    public function testRejectsInvalidTenantIdentifiers(): void
    {
        $strategy = new PathStrategy(0);

        // Identifier with special characters
        $request1 = $this->createMock(Request::class);
        $request1->method('getURI')->willReturn('/tenant@123/api');

        // Identifier starting with hyphen
        $request2 = $this->createMock(Request::class);
        $request2->method('getURI')->willReturn('/-invalid/api');

        // Identifier ending with hyphen
        $request3 = $this->createMock(Request::class);
        $request3->method('getURI')->willReturn('/invalid-/api');

        $this->assertNull($strategy->resolve($request1));
        $this->assertNull($strategy->resolve($request2));
        $this->assertNull($strategy->resolve($request3));
    }

    public function testAcceptsValidTenantIdentifiers(): void
    {
        $strategy = new PathStrategy(0);

        // Single character
        $request1 = $this->createMock(Request::class);
        $request1->method('getURI')->willReturn('/a/api');

        // With hyphens
        $request2 = $this->createMock(Request::class);
        $request2->method('getURI')->willReturn('/my-tenant/api');

        // Alphanumeric
        $request3 = $this->createMock(Request::class);
        $request3->method('getURI')->willReturn('/tenant123/api');

        $this->assertSame('a', $strategy->resolve($request1));
        $this->assertSame('my-tenant', $strategy->resolve($request2));
        $this->assertSame('tenant123', $strategy->resolve($request3));
    }

    public function testHandlesQueryString(): void
    {
        $strategy = new PathStrategy(0);

        $request = $this->createMock(Request::class);
        $request->method('getURI')->willReturn('/acme/api?param=value');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testGetStrategyNameReturnsPath(): void
    {
        $strategy = new PathStrategy();

        $this->assertSame('path', $strategy->getStrategyName());
    }

    public function testGetPositionReturnsConfiguredPosition(): void
    {
        $strategy = new PathStrategy(2);

        $this->assertSame(2, $strategy->getPosition());
    }

    public function testGetPrefixReturnsConfiguredPrefix(): void
    {
        $strategy = new PathStrategy(0, 'organizations');

        $this->assertSame('organizations', $strategy->getPrefix());
    }
}
