<?php

namespace Jav\ApiTopiaBundle\Loader;

use Jav\ApiTopiaBundle\Api\Attributes\Rest\Attribute;
use Jav\ApiTopiaBundle\Api\Attributes\Rest\Get;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader extends Loader
{
    private $isLoaded = false;

    /** @var string[] */
    private $resolvers = [];

    public function setResolvers(array $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function load($resource, string $type = null)
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "apitopia" loader twice');
        }

        $routes = new RouteCollection();

        foreach ($this->resolvers as $resolverClass) {
            $reflection = new \ReflectionClass($resolverClass);

            foreach ($reflection->getMethods() as $method) {
                $restAttributes = $method->getAttributes(Attribute::class, \ReflectionAttribute::IS_INSTANCEOF);

                foreach ($restAttributes as $attribute) {
                    /** @var Attribute $attributeInstance */
                    $attributeInstance = $attribute->newInstance();

                    $route = new Route($attributeInstance->path, [
                        '_controller' => 'Jav\ApiTopiaBundle\Rest\ResponseHandler::handleResponse',
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

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, string $type = null)
    {
        return $type === 'apitopia';
    }
}
