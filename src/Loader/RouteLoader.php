<?php

namespace Jav\ApiTopiaBundle\Loader;

use Jav\ApiTopiaBundle\Api\Attributes\Rest\Attribute;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader extends Loader
{
    private bool $isLoaded = false;

    /** @var string[] */
    private array $resolvers = [];

    /** @var string[] */
    private array $graphQLEndpoints = [];

    /**
     * @param string[] $resolvers
     */
    public function setResolvers(array $resolvers): void
    {
        $this->resolvers = $resolvers;
    }

    /**
     * @param string[] $endpoints
     */
    public function setGraphQLEndpoints(array $endpoints): void
    {
        $this->graphQLEndpoints = $endpoints;
    }

    public function load(mixed $resource, string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "apitopia" loader twice');
        }

        $routes = new RouteCollection();

        $this->loadRestRoutes($routes);
        $this->loadGraphQLEndpoints($routes);
        $this->loadGraphiQLEndpoint($routes);
        $this->isLoaded = true;

        return $routes;
    }

    private function loadGraphQLEndpoints(RouteCollection $routes): void
    {
        foreach ($this->graphQLEndpoints as $schemaName =>  $endpoint) {
            $routes->add('apitopia_graphql_' . $schemaName, new Route($endpoint, [
                '_controller' => 'Jav\ApiTopiaBundle\GraphQL\RequestHandler::handleRequest',
                '_apitopia' => [
                    'schema' => $schemaName,
                    'endpoint' => $endpoint
                ]
            ], methods: ['POST']));
        }
    }

    private function loadGraphiQLEndpoint(RouteCollection $routes): void
    {
        $routes->add('apitopia_graphiql', new Route('/api/graphiql/{schema}', [
            '_controller' => 'Jav\ApiTopiaBundle\Controller\GraphiQLController::index',
        ], methods: ['GET', 'POST']));
    }

    private function loadRestRoutes(RouteCollection $routes): void
    {
        foreach ($this->resolvers as $resolverClass) {
            $reflection = new \ReflectionClass($resolverClass);

            foreach ($reflection->getMethods() as $method) {
                $restAttributes = $method->getAttributes(Attribute::class, \ReflectionAttribute::IS_INSTANCEOF);

                foreach ($restAttributes as $attribute) {
                    /** @var Attribute $attributeInstance */
                    $attributeInstance = $attribute->newInstance();

                    $route = new Route($attributeInstance->path, [
                        '_controller' => 'Jav\ApiTopiaBundle\Rest\RequestHandler::handleResponse',
                        '_apitopia' => [
                            'attr' => serialize($attributeInstance),
                            'resolver' => [
                                'class' => $resolverClass,
                                'method' => $method->getName()
                            ]
                        ]
                    ]);

                    $route->setMethods([$attributeInstance->method]);
                    $routes->add($attributeInstance->name, $route);
                }
            }
        }
    }

    public function supports($resource, string $type = null): bool
    {
        return $type === 'apitopia';
    }
}
