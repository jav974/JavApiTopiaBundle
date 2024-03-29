<?php

namespace Jav\ApiTopiaBundle\DependencyInjection;

use Exception;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\SubscriptionResolverInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class ApiTopiaExtension extends ConfigurableExtension
{
    /**
     * @param array<string, mixed> $mergedConfig
     * @throws Exception
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(QueryItemResolverInterface::class)
            ->addTag('apitopia.graphql_resolver');
        $container->registerForAutoconfiguration(QueryCollectionResolverInterface::class)
            ->addTag('apitopia.graphql_resolver');
        $container->registerForAutoconfiguration(MutationResolverInterface::class)
            ->addTag('apitopia.graphql_resolver');
        $container->registerForAutoconfiguration(SubscriptionResolverInterface::class)
            ->addTag('apitopia.graphql_resolver');
        $container->registerForAutoconfiguration(DeferredResolverInterface::class)
            ->addTag('apitopia.graphql_resolver');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $container
            ->getDefinition('jav_apitopia.graphql.schema_builder')
            ->addMethodCall('setConfig', [$mergedConfig['schemas'] ?? []])
            ->addMethodCall('setSchemaOutputDirectory', [$mergedConfig['schema_output_dir']]);

        $container
            ->getDefinition('jav_apitopia.cache.resources_warmer')
            ->addMethodCall('setConfig', [$mergedConfig['schemas'] ?? []]);

        $graphQLEndpoints = [];

        foreach ($mergedConfig['schemas'] as $schemaName => $schema) {
            $graphQLEndpoints[$schemaName] = $schema['path'];
        }

        $container
            ->getDefinition('jav_apitopia.loader.route')
            ->addMethodCall('setGraphQLEndpoints', [$graphQLEndpoints]);

        $container
            ->getDefinition('jav_apitopia.controller.graphiql')
            ->setArgument('$endpoints', $graphQLEndpoints);
    }
}
