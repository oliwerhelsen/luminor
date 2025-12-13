<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy\Strategy;

use Luminor\DDD\Multitenancy\Strategy\SubdomainStrategy;
use PHPUnit\Framework\TestCase;
use Utopia\Http\Request;

final class SubdomainStrategyTest extends TestCase
{
    public function testResolveFromSubdomain(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $request = $this->createMock(Request::class);
        $request->method('getHostname')->willReturn('acme.example.com');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testReturnsNullForBaseDomain(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $request = $this->createMock(Request::class);
        $request->method('getHostname')->willReturn('example.com');

        $this->assertNull($strategy->resolve($request));
    }

    public function testReturnsNullForDifferentDomain(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $request = $this->createMock(Request::class);
        $request->method('getHostname')->willReturn('acme.other-domain.com');

        $this->assertNull($strategy->resolve($request));
    }

    public function testExcludesDefaultSubdomains(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $wwwRequest = $this->createMock(Request::class);
        $wwwRequest->method('getHostname')->willReturn('www.example.com');

        $apiRequest = $this->createMock(Request::class);
        $apiRequest->method('getHostname')->willReturn('api.example.com');

        $adminRequest = $this->createMock(Request::class);
        $adminRequest->method('getHostname')->willReturn('admin.example.com');

        $this->assertNull($strategy->resolve($wwwRequest));
        $this->assertNull($strategy->resolve($apiRequest));
        $this->assertNull($strategy->resolve($adminRequest));
    }

    public function testCustomExcludedSubdomains(): void
    {
        $strategy = new SubdomainStrategy('example.com', ['staging', 'dev']);

        $stagingRequest = $this->createMock(Request::class);
        $stagingRequest->method('getHostname')->willReturn('staging.example.com');

        $devRequest = $this->createMock(Request::class);
        $devRequest->method('getHostname')->willReturn('dev.example.com');

        $acmeRequest = $this->createMock(Request::class);
        $acmeRequest->method('getHostname')->willReturn('acme.example.com');

        $this->assertNull($strategy->resolve($stagingRequest));
        $this->assertNull($strategy->resolve($devRequest));
        $this->assertSame('acme', $strategy->resolve($acmeRequest));
    }

    public function testHandlesNestedSubdomains(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $request = $this->createMock(Request::class);
        $request->method('getHostname')->willReturn('app.acme.example.com');

        $this->assertSame('acme', $strategy->resolve($request));
    }

    public function testReturnsNullForEmptyHostname(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $request = $this->createMock(Request::class);
        $request->method('getHostname')->willReturn('');

        $this->assertNull($strategy->resolve($request));
    }

    public function testGetStrategyNameReturnsSubdomain(): void
    {
        $strategy = new SubdomainStrategy('example.com');

        $this->assertSame('subdomain', $strategy->getStrategyName());
    }

    public function testCaseInsensitiveExcludedSubdomains(): void
    {
        $strategy = new SubdomainStrategy('example.com', ['WWW', 'API']);

        $wwwRequest = $this->createMock(Request::class);
        $wwwRequest->method('getHostname')->willReturn('www.example.com');

        $this->assertNull($strategy->resolve($wwwRequest));
    }
}
