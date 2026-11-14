<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\DependencyInjection\Compiler\NodeResolverPass;
use Jav\ApiTopiaBundle\GraphQL\NodeResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class NodeResolverPassTest extends TestCase
{
    public function testNoResolver(): void
    {
        $container = new ContainerBuilder();
        (new NodeResolverPass())->process($container);

        $this->assertFalse($container->has(NodeResolverPass::SERVICE_ID));
    }

    public function testSingleTaggedResolverIsAliased(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.node_resolver')->addTag(NodeResolverPass::TAG);
        (new NodeResolverPass())->process($container);

        $this->assertSame('app.node_resolver', (string) $container->getAlias(NodeResolverPass::SERVICE_ID));
    }

    public function testInterfaceAliasIsUsed(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.node_resolver');
        $container->setAlias(NodeResolverInterface::class, 'app.node_resolver');
        (new NodeResolverPass())->process($container);

        $this->assertSame(NodeResolverInterface::class, (string) $container->getAlias(NodeResolverPass::SERVICE_ID));
    }

    public function testExplicitServiceIsKept(): void
    {
        $container = new ContainerBuilder();
        $container->register(NodeResolverPass::SERVICE_ID);
        $container->register('app.a')->addTag(NodeResolverPass::TAG);
        $container->register('app.b')->addTag(NodeResolverPass::TAG);
        (new NodeResolverPass())->process($container);

        $this->assertFalse($container->hasAlias(NodeResolverPass::SERVICE_ID));
    }

    public function testSeveralResolversAreRejected(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.a')->addTag(NodeResolverPass::TAG);
        $container->register('app.b')->addTag(NodeResolverPass::TAG);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"app.a", "app.b"');
        (new NodeResolverPass())->process($container);
    }
}
