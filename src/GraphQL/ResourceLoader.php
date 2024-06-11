<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Doctrine\Common\Annotations\AnnotationReader;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Throwable;

class ResourceLoader
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $resources = [];
    private static ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(private readonly string $cacheDir)
    {
        if (class_exists('Symfony\Component\Serializer\Mapping\Loader\AttributeLoader')) {
            self::$classMetadataFactory = new ClassMetadataFactory(new \Symfony\Component\Serializer\Mapping\Loader\AttributeLoader());
        } else {
            self::$classMetadataFactory = new ClassMetadataFactory(new \Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader(
                Kernel::VERSION_ID < 60400 ? new AnnotationReader() : null
            ));
        }
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

        self::$resources[$schemaName] = [];

        foreach ($resourceDirectories as $resourceDirectory) {
            $finder = (new Finder())->files()->in($resourceDirectory)->name('*.php');

            foreach ($finder as $file) {
                $info = $this->getInfoFromFile($file);
                $namespace = $info['namespace'];
                $className = $file->getBasename('.php');
                $classPath = $namespace . '\\' . $className;

                try {
                    if ($info['isClass']) {
                        $metadata = self::$classMetadataFactory->getMetadataFor($classPath);
                        $reflectionClass = $metadata->getReflectionClass();
                        /** @var ApiResource|null $apiResource */
                        $apiResource = ($reflectionClass->getAttributes(ApiResource::class)[0] ?? null)?->newInstance();
                        $queries = array_filter($apiResource?->queries ?? [], fn($query) => !$query instanceof QueryCollection);
                        $queryCollections = array_filter($apiResource?->queries ?? [], fn($query) => $query instanceof QueryCollection);

                        self::$resources[$schemaName][$classPath] = [
                            'type' => 'class',
                            'name' => $className,
                            'queries' => $queries,
                            'query_collections' => $queryCollections,
                            'mutations' => $apiResource?->mutations ?? [],
                            'subscriptions' => $apiResource?->subscriptions ?? [],
                        ];
                    } elseif ($info['isEnum']) {
                        self::$resources[$schemaName][$classPath] = [
                            'type' => 'enum',
                            'name' => $className,
                        ];
                    }
                } catch (Throwable) {
                    // We might have a file that is not a resource, not even a class, so we just ignore it
                }
            }
        }

        file_put_contents($resourcesCacheFilename, serialize(self::$resources[$schemaName]));
    }

    /**
     * @return array<string, mixed>
     */
    private function getInfoFromFile(SplFileInfo $file): array
    {
        $content = $file->getContents();

        preg_match('/namespace\s+(.+);/', $content, $namespaceMatches);
        $isClass = preg_match('/class\s+(.+)\s+/', $content, $classMatches) > 0;
        $isTrait = preg_match('/trait\s+(.+)\s+/', $content, $traitMatches) > 0;
        $isEnum = preg_match('/enum\s+(.+)\s+/', $content, $enumMatches) > 0;

        return [
            'namespace' => $namespaceMatches[1] ?? '',
            'isClass' => $isClass,
            'className' => $classMatches[1] ?? '',
            'isTrait' => $isTrait,
            'traitName' => $traitMatches[1] ?? '',
            'isEnum' => $isEnum,
            'enumName' => $enumMatches[1] ?? '',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getResources(string $schemaName, string $type = 'class'): array
    {
        $collection = self::$resources[$schemaName] ?? [];

        return array_filter($collection, fn ($resource) => $resource['type'] === $type);
    }

    /**
     * @return ReflectionClass<object>|ReflectionEnum<object>|null
     * @throws ReflectionException
     */
    public function getReflectionClass(string $schemaName, string $classPath): ReflectionClass|ReflectionEnum|null
    {
        if (isset(self::$resources[$schemaName][$classPath])) {
            if (self::$resources[$schemaName][$classPath]['type'] === 'enum') {
                return self::$resources[$schemaName][$classPath]['reflection'] ??= new ReflectionEnum($classPath);
            }

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
