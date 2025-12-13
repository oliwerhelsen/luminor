<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy;

use Luminor\DDD\Multitenancy\TenantResolver;
use Luminor\DDD\Multitenancy\TenantResolverInterface;
use PHPUnit\Framework\TestCase;
use Utopia\Http\Request;

final class TenantResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenNoResolvers(): void
    {
        $resolver = new TenantResolver();
        $request = $this->createMock(Request::class);

        $this->assertNull($resolver->resolve($request));
    }

    public function testResolveUsesFirstSuccessfulResolver(): void
    {
        $resolver1 = $this->createMock(TenantResolverInterface::class);
        $resolver1->method('resolve')->willReturn(null);

        $resolver2 = $this->createMock(TenantResolverInterface::class);
        $resolver2->method('resolve')->willReturn('tenant-from-resolver-2');

        $resolver3 = $this->createMock(TenantResolverInterface::class);
        $resolver3->expects($this->never())->method('resolve');

        $compositeResolver = new TenantResolver([$resolver1, $resolver2, $resolver3]);
        $request = $this->createMock(Request::class);

        $this->assertSame('tenant-from-resolver-2', $compositeResolver->resolve($request));
    }

    public function testAddResolverAddsToChain(): void
    {
        $resolver = new TenantResolver();

        $innerResolver = $this->createMock(TenantResolverInterface::class);
        $innerResolver->method('resolve')->willReturn('test-tenant');

        $resolver->addResolver($innerResolver);

        $request = $this->createMock(Request::class);
        $this->assertSame('test-tenant', $resolver->resolve($request));
    }

    public function testGetStrategyNameReturnsComposite(): void
    {
        $resolver = new TenantResolver();

        $this->assertSame('composite', $resolver->getStrategyName());
    }

    public function testGetResolversReturnsConfiguredResolvers(): void
    {
        $resolver1 = $this->createMock(TenantResolverInterface::class);
        $resolver2 = $this->createMock(TenantResolverInterface::class);

        $compositeResolver = new TenantResolver([$resolver1, $resolver2]);

        $this->assertCount(2, $compositeResolver->getResolvers());
        $this->assertSame($resolver1, $compositeResolver->getResolvers()[0]);
        $this->assertSame($resolver2, $compositeResolver->getResolvers()[1]);
    }
}
