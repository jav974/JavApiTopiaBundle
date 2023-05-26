<?php

namespace Jav\ApiTopiaBundle\DependencyInjection;

use Exception;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Api\ResolverInterface;
use Jav\ApiTopiaBundle\Controller\GraphiQLController;
use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Jav\ApiTopiaBundle\Loader\RouteLoader;
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
        $container->registerForAutoconfiguration(ResolverInterface::class)
            ->addTag('apitopia.resolver')
        ;
        $container->registerForAutoconfiguration(QueryItemResolverInterface::class)
            ->addTag('apitopia.graphql_resolver')
        ;
        $container->registerForAutoconfiguration(QueryCollectionResolverInterface::class)
            ->addTag('apitopia.graphql_resolver')
        ;
        $container->registerForAutoconfiguration(MutationResolverInterface::class)
            ->addTag('apitopia.graphql_resolver')
        ;

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $container->getDefinition(SchemaBuilder::class)
            ->addMethodCall('setConfig', [$mergedConfig['schemas'] ?? []])
            ->addMethodCall('setSchemaOutputDirectory', [$mergedConfig['schema_output_dir']])
        ;

        $graphQLEndpoints = [];

        foreach ($mergedConfig['schemas'] as $schemaName => $schema) {
            $graphQLEndpoints[$schemaName] = $schema['path'];
        }

        $container->getDefinition(RouteLoader::class)
            ->addMethodCall('setGraphQLEndpoints', [$graphQLEndpoints])
        ;

        $container->getDefinition(GraphiQLController::class)
            ->setArgument('$endpoints', $graphQLEndpoints)
        ;
    }
}
