<?php

namespace Jav\ApiTopiaBundle\Tests;

use Jav\ApiTopiaBundle\JavApiTopiaBundle;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredCompanyResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredOrdersResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FailingResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\TestNodeResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredAddressResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FavoriteProductsResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\StatResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UsersResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2CollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2ItemResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectCollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectItemResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\FileUploadMutationResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\SimpleMutationResolver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class JavApiTopiaTestKernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Environments:
     * - "test": default bundle configuration (backward compatible behaviour)
     * - "test_strict": hardened configuration (no debug, spec status codes, limits, no introspection nor GraphiQL)
     */
    public function __construct(string $environment = 'test', bool $debug = true)
    {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/apitopia-tests/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/apitopia-tests/' . $this->environment . '/log';
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new MercureBundle(),
            new JavApiTopiaBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('kernel.secret', 'apitopia_test');

            $frameworkConfig = [
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true
                ],
                'router' => [
                    'utf8' => true,
                    'type' => 'apitopia',
                    'resource' => '.'
                ]
            ];

            $container->loadFromExtension('framework', $frameworkConfig);

            $jwt = [
                'secret' => '!ChangeThisMercureHubJWTSecretKey!',
                'publish' => '*',
                'subscribe' => '*'
            ];

            // symfony/mercure-bundle >= 0.5 (protocol 1.0) requires RFC 9068 claims
            if (enum_exists(\Symfony\Component\Mercure\ProtocolVersion::class)) {
                $jwt['claims'] = ['iss' => 'apitopia-tests', 'sub' => 'apitopia-tests', 'client_id' => 'apitopia-tests'];
            }

            $container->loadFromExtension('mercure', [
                'hubs' => [
                    'default' => [
                        'url' => 'http://localhost:8000/.well-known/mercure',
                        'public_url' => 'http://localhost:8000/.well-known/mercure',
                        'jwt' => $jwt,
                    ]
                ]
            ]);

            $apiTopiaConfig = [
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
            ];

            if ($this->environment === 'test_strict') {
                $apiTopiaConfig += [
                    'debug' => false,
                    'error_http_status_code' => 200,
                    'introspection' => false,
                    'max_query_depth' => 2,
                    'pagination' => ['default_limit' => 2, 'max_limit' => 5],
                    'graphiql' => ['enabled' => false],
                ];
            }

            $container->loadFromExtension('api_topia', $apiTopiaConfig);
            $container->loadFromExtension('twig', []);

            $container->register(FailingResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(DeferredCompanyResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(DeferredOrdersResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(TestNodeResolver::class)->setAutoconfigured(true);
            $container->register(UserResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(UsersResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(FavoriteProductsResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(StatResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(DeferredAddressResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObjectItemResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObjectCollectionResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObject2ItemResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(ApiResourceObject2CollectionResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(SimpleMutationResolver::class)->addTag('apitopia.graphql_resolver');
            $container->register(FileUploadMutationResolver::class)->addTag('apitopia.graphql_resolver');
        });
    }
}
