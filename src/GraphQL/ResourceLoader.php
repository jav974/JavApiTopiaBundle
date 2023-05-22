<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ResourceLoader
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $resources = [];

    /**
     * @param string[] $resourceDirectories
     */
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
                    $reflectionClass = new ReflectionClass($classPath);
                    /** @var ApiResource|null $apiResource */
                    $apiResource = ($reflectionClass->getAttributes(ApiResource::class)[0] ?? null)?->newInstance();
                    $queries = array_filter($apiResource?->queries ?? [], fn($query) => !$query instanceof QueryCollection);
                    $queryCollections = array_filter($apiResource?->queries ?? [], fn($query) => $query instanceof QueryCollection);

                    $this->resources[$schemaName][$classPath] = [
                        'name' => $className,
                        'queries' => $queries,
                        'query_collections' => $queryCollections,
                        'mutations' => $apiResource?->mutations ?? [],
                        'subscriptions' => $apiResource?->subscriptions ?? [],
                        'reflection' => $reflectionClass
                    ];
                } catch (\ReflectionException) {
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getResources(string $schemaName): array
    {
        return $this->resources[$schemaName] ?? [];
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public function getReflectionClass(string $schemaName, string $classPath): ?ReflectionClass
    {
        return $this->resources[$schemaName][$classPath]['reflection'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
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
