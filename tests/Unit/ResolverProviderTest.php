<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\GraphQL\MercureUrlGenerator;
use Jav\ApiTopiaBundle\GraphQL\ResolverProvider;
use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredAddressResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ResolverProviderTest extends TestCase
{
    private ResolverProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ResolverProvider(
            new ServiceLocator([
                UserResolver::class => fn () => new UserResolver(),
                DeferredAddressResolver::class => fn () => new DeferredAddressResolver(),
            ]),
            new Serializer(new ResourceLoader(sys_get_temp_dir())),
            new MercureUrlGenerator()
        );
    }

    public function testResolvers(): void
    {
        $this->assertInstanceOf(UserResolver::class, $this->provider->getResolver(UserResolver::class));
        $this->assertInstanceOf(DeferredAddressResolver::class, $this->provider->getDeferredResolver(DeferredAddressResolver::class));
    }

    public function testUnknownResolver(): void
    {
        $this->expectExceptionMessage('Resolver "Nope" not found.');
        $this->provider->getResolver('Nope');
    }

    public function testUnknownDeferredResolver(): void
    {
        $this->expectExceptionMessage('Resolver "Nope" not found.');
        $this->provider->getDeferredResolver('Nope');
    }

    public function testDeferredResolverUsedAsRegularResolver(): void
    {
        $this->expectExceptionMessage('is a DeferredResolverInterface');
        $this->provider->getResolver(DeferredAddressResolver::class);
    }

    public function testRegularResolverUsedAsDeferredResolver(): void
    {
        $this->expectExceptionMessage('is not a DeferredResolverInterface');
        $this->provider->getDeferredResolver(UserResolver::class);
    }
}
