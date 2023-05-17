<?php

namespace Jav\ApiTopiaBundle\DependencyInjection\Compiler;

use Jav\ApiTopiaBundle\Loader\RouteLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $resolverIds = array_keys($container->findTaggedServiceIds('apitopia.resolver'));
        $classes = [];

        foreach ($resolverIds as $resolverId) {
            $definition = $container->findDefinition($resolverId);
            $class = $definition->getClass();
            $classes[] = $class;
        }

        $routeLoaderDefinition = $container->getDefinition(RouteLoader::class);
        $routeLoaderDefinition->addMethodCall('setResolvers', [$classes]);
    }
}
