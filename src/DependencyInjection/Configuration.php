<?php

namespace Jav\ApiTopiaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('api_topia');

        $builder->getRootNode()
            ->children()
                ->scalarNode('schema_output_dir')->defaultValue('%kernel.project_dir%')->end()
                ->arrayNode('schemas')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->end()
                            ->arrayNode('resource_directories')->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $builder;
    }
}
