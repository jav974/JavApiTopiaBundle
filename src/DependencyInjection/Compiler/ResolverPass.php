<?php

namespace Jav\ApiTopiaBundle\DependencyInjection\Compiler;

use Jav\ApiTopiaBundle\Loader\RouteLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ResolverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $resolverIds = array_keys($container->findTaggedServiceIds('apitopia.resolver'));
        $classes = [];

        foreach ($resolverIds as $resolverId) {
            $definition = $container->findDefinition($resolverId);
            $class = $definition->getClass();
            $classes[] = $class;

            // Introspect the class to extract graphql attributes
        }

        $routeLoaderDefinition = $container->getDefinition(RouteLoader::class);
        $routeLoaderDefinition->addMethodCall('setResolvers', [$classes]);
    }
}
