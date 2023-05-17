<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Symfony\Component\Finder\Finder;

class ResourceLoader
{
    private array $resources = [];

    public function loadResources(string $schemaName, array $resourceDirectories): void
    {
        if (isset($this->resources[$schemaName])) {
            return ;
        }

        foreach ($resourceDirectories as $resourceDirectory) {
            $finder = (new Finder())->files()->in($resourceDirectory)->name('*.php');

            foreach ($finder as $file) {
                preg_match('/namespace\s+(.+);/', $file->getContents(), $matches);
                $namespace = $matches[1] ?? '';
                $className = $file->getBasename('.php');
                $classPath = $namespace . '\\' . $className;

                try {
                    $reflectionClass = new \ReflectionClass($classPath);

                    $this->resources[$schemaName][$classPath] = [
                        'name' => $className,
                        'queries' => $reflectionClass->getAttributes(Query::class),
                        'query_collections' => $reflectionClass->getAttributes(QueryCollection::class),
                        'mutations' => $reflectionClass->getAttributes(Mutation::class),
                        'subscriptions' => [],
                        'reflection' => $reflectionClass
                    ];
                } catch (\ReflectionException) {
                }
            }
        }
    }

    public function getResources(string $schemaName): array
    {
        return $this->resources[$schemaName] ?? [];
    }

    public function getReflectionClass(string $schemaName, string $classPath): ?\ReflectionClass
    {
        return $this->resources[$schemaName][$classPath]['reflection'] ?? null;
    }

    public function getResourceByClassName(string $schemaName, string $className): ?array
    {
        foreach ($this->resources[$schemaName] ?? [] as $resource) {
            if ($resource['name'] === $className) {
                return $resource;
            }
        }

        return null;
    }
}
