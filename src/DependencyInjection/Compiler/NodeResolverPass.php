<?php

namespace Jav\ApiTopiaBundle\DependencyInjection\Compiler;

use Jav\ApiTopiaBundle\GraphQL\NodeResolverInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Plugs the application's NodeResolverInterface implementation (if any) into the Relay "node" field.
 */
final class NodeResolverPass implements CompilerPassInterface
{
    public const TAG = 'apitopia.node_resolver';
    public const SERVICE_ID = 'jav_apitopia.graphql.node_resolver';

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(self::SERVICE_ID)) {
            return;
        }

        $tagged = array_keys($container->findTaggedServiceIds(self::TAG));

        if (count($tagged) > 1) {
            throw new LogicException(sprintf(
                'Only one service may implement "%s", found: "%s". Alias "%s" to the one you want to use.',
                NodeResolverInterface::class,
                implode('", "', $tagged),
                self::SERVICE_ID
            ));
        }

        if (count($tagged) === 1) {
            $container->setAlias(self::SERVICE_ID, $tagged[0]);
        } elseif ($container->has(NodeResolverInterface::class)) {
            $container->setAlias(self::SERVICE_ID, NodeResolverInterface::class);
        }
    }
}
