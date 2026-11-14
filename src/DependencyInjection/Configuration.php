<?php

namespace Jav\ApiTopiaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('api_topia');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $builder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('schema_output_dir')->defaultValue('%kernel.project_dir%')->end()
                ->scalarNode('debug')
                    ->info('Expose exception messages and stack traces in GraphQL errors. Never enable this in production.')
                    ->defaultValue('%kernel.debug%')
                ->end()
                ->integerNode('error_http_status_code')
                    ->info('HTTP status code returned when the GraphQL result contains errors. Use 200 to follow the GraphQL-over-HTTP convention (500 is kept as default for backward compatibility).')
                    ->defaultValue(500)
                    ->min(200)->max(599)
                ->end()
                ->booleanNode('batching')
                    ->info('Allow batched queries (an array of operations in a single HTTP request).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('introspection')
                    ->info('Allow schema introspection queries.')
                    ->defaultTrue()
                ->end()
                ->integerNode('max_query_depth')
                    ->info('Maximum depth of a query (0 disables the check).')
                    ->defaultValue(15)
                    ->min(0)
                ->end()
                ->integerNode('max_query_complexity')
                    ->info('Maximum complexity of a query (0 disables the check).')
                    ->defaultValue(0)
                    ->min(0)
                ->end()
                ->arrayNode('pagination')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('default_limit')
                            ->info('Page size used when the client does not provide "first", "last" or "limit".')
                            ->defaultValue(50)
                            ->min(1)
                        ->end()
                        ->integerNode('max_limit')
                            ->info('Maximum page size a client may request (0 disables the cap).')
                            ->defaultValue(1000)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('graphiql')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('enabled')
                            ->info('Expose the GraphiQL IDE on /api/graphiql/{schema}.')
                            ->defaultValue('%kernel.debug%')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('schemas')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('resource_directories')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $builder;
    }
}
