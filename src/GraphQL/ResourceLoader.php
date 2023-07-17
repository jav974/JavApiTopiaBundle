<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Doctrine\Common\Annotations\AnnotationReader;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Throwable;

class ResourceLoader
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $resources = [];
    private static ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(private readonly string $cacheDir)
    {
        self::$classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
    }

    public function getClassMetatadaFactory(): ClassMetadataFactoryInterface
    {
        return self::$classMetadataFactory;
    }

    /**
     * @param string[] $resourceDirectories
     */
    public function loadResources(string $schemaName, array $resourceDirectories, bool $invalidateCache = false): void
    {
        if (isset(self::$resources[$schemaName])) {
            return ;
        }

        if (!is_dir("$this->cacheDir/apitopia")) {
            mkdir("$this->cacheDir/apitopia", 0777, true);
        }

        $resourcesCacheFilename = "$this->cacheDir/apitopia/resources.$schemaName.php";

        if ($invalidateCache && file_exists($resourcesCacheFilename)) {
            unlink($resourcesCacheFilename);
        }

        if (file_exists($resourcesCacheFilename)) {
            self::$resources[$schemaName] = unserialize(file_get_contents($resourcesCacheFilename));
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
                    $metadata = self::$classMetadataFactory->getMetadataFor($classPath);
                    $reflectionClass = $metadata->getReflectionClass();
                    /** @var ApiResource|null $apiResource */
                    $apiResource = ($reflectionClass->getAttributes(ApiResource::class)[0] ?? null)?->newInstance();
                    $queries = array_filter($apiResource?->queries ?? [], fn($query) => !$query instanceof QueryCollection);
                    $queryCollections = array_filter($apiResource?->queries ?? [], fn($query) => $query instanceof QueryCollection);

                    self::$resources[$schemaName][$classPath] = [
                        'name' => $className,
                        'queries' => $queries,
                        'query_collections' => $queryCollections,
                        'mutations' => $apiResource?->mutations ?? [],
                        'subscriptions' => $apiResource?->subscriptions ?? [],
                    ];
                } catch (Throwable) {
                    // We might have a file that is not a resource, not even a class, so we just ignore it
                }
            }
        }

        file_put_contents($resourcesCacheFilename, serialize(self::$resources[$schemaName]));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getResources(string $schemaName): array
    {
        return self::$resources[$schemaName] ?? [];
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public function getReflectionClass(string $schemaName, string $classPath): ?ReflectionClass
    {
        if (isset(self::$resources[$schemaName][$classPath])) {
            return self::$resources[$schemaName][$classPath]['reflection'] ??= self::$classMetadataFactory
                ->getMetadataFor($classPath)
                ->getReflectionClass();
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResourceByClassName(string $schemaName, string $className): ?array
    {
        foreach (self::$resources[$schemaName] ?? [] as $classPath => &$resource) {
            if ($resource['name'] === $className) {
                $resource['reflection'] = $this->getReflectionClass($schemaName, $classPath);
                return $resource;
            }
        }

        return null;
    }
}
