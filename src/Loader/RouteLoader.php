<?php

namespace Jav\ApiTopiaBundle\Loader;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader extends Loader
{
    private bool $isLoaded = false;

    /** @var string[] */
    private array $graphQLEndpoints = [];

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

        $this->loadGraphQLEndpoints($routes);
        $this->loadGraphiQLEndpoint($routes);
        $this->isLoaded = true;

        return $routes;
    }

    private function loadGraphQLEndpoints(RouteCollection $routes): void
    {
        foreach ($this->graphQLEndpoints as $schemaName => $endpoint) {
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

    public function supports($resource, string $type = null): bool
    {
        return $type === 'apitopia';
    }
}
