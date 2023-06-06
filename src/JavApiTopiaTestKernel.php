<?php

namespace Jav\ApiTopiaBundle;

use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FavoriteProductsResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\StatResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2CollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2ItemResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectCollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectItemResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\FileUploadMutationResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\SimpleMutationResolver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class JavApiTopiaTestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new MercureBundle(),
            new JavApiTopiaBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('kernel.secret', 'apitopia_test');

            $container->loadFromExtension('framework', [
                'test' => true,
                'http_method_override' => false,
                'router' => [
                    'utf8' => true,
                    'type' => 'apitopia',
                    'resource' => '.'
                ]
            ]);

            $container->loadFromExtension('mercure', [
                'hubs' => [
                    'default' => [
                        'url' => 'http://localhost:8000/.well-known/mercure',
                        'public_url' => 'http://localhost:8000/.well-known/mercure',
                        'jwt' => [
                            'secret' => '!ChangeThisMercureHubJWTSecretKey!',
                            'publish' => '*',
                            'subscribe' => '*'
                        ]
                    ]
                ]
            ]);

            $container->loadFromExtension('api_topia', [
                'schema_output_dir' => '%kernel.project_dir%/tests/GraphQL/Output',
                'schemas' => [
                    'test1' => [
                        'resource_directories' => ['%kernel.project_dir%/tests/GraphQL/Schema/Test1/DTO'],
                        'path' => '/test/graphql/test1'
                    ],
                    'test2' => [
                        'resource_directories' => ['%kernel.project_dir%/tests/GraphQL/Schema/Test2/DTO'],
                        'path' => '/test/graphql/test2'
                    ],
                ]
            ]);

            $container->register(UserResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(FavoriteProductsResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(StatResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObjectItemResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObjectCollectionResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObject2ItemResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObject2CollectionResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(SimpleMutationResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(FileUploadMutationResolver::class)->addTag('apitopia.graphql_resolver');
        });
    }
}
