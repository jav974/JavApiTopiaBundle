<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use PhpToken;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

class ResourceLoader
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $resources = [];
    private readonly ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $debug = false,
    ) {
        $this->classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
    }

    public function getClassMetatadaFactory(): ClassMetadataFactoryInterface
    {
        return $this->classMetadataFactory;
    }

    /**
     * Scans the resource directories of a schema for classes and enums.
     * The list of discovered classes is cached; in debug mode, the cache is refreshed whenever a resource directory changes.
     *
     * @param string[] $resourceDirectories
     */
    public function loadResources(string $schemaName, array $resourceDirectories, bool $invalidateCache = false): void
    {
        if (isset($this->resources[$schemaName]) && !$invalidateCache) {
            return;
        }

        // The directories are part of the key: changing them must not reuse a cache built from other directories
        $key = $schemaName . '.' . substr(hash('xxh128', serialize($resourceDirectories)), 0, 16);
        $cache = new ConfigCache("$this->cacheDir/apitopia/resources.$key.php", $this->debug);

        if ($invalidateCache || !$cache->isFresh()) {
            $classes = $this->discoverClasses($resourceDirectories);
            $cache->write(
                '<?php return ' . var_export($classes, true) . ';' . PHP_EOL,
                array_map(fn (string $directory) => new DirectoryResource($directory, '/\.php$/'), $resourceDirectories)
            );
        } else {
            $classes = require $cache->getPath();
        }

        $this->resources[$schemaName] = [];
        $shortNames = [];

        foreach ($classes as $classPath => $type) {
            $resource = $type === 'enum'
                ? ['type' => 'enum', 'name' => (new ReflectionEnum($classPath))->getShortName()]
                : $this->buildClassResource($classPath);

            // GraphQL type names are derived from short class names, they must be unique within a schema
            if (isset($shortNames[$resource['name']])) {
                throw new \LogicException(sprintf(
                    'Classes "%s" and "%s" would both produce the GraphQL type "%s" in schema "%s". Rename one of them or move it to another schema.',
                    $shortNames[$resource['name']],
                    $classPath,
                    $resource['name'],
                    $schemaName
                ));
            }

            $shortNames[$resource['name']] = $classPath;
            $this->resources[$schemaName][$classPath] = $resource;
        }
    }

    /**
     * @param string[] $resourceDirectories
     * @return array<class-string, 'class'|'enum'>
     */
    private function discoverClasses(array $resourceDirectories): array
    {
        $classes = [];

        foreach ($resourceDirectories as $resourceDirectory) {
            $finder = (new Finder())->files()->in($resourceDirectory)->name('*.php')->sortByName();

            foreach ($finder as $file) {
                $declaration = self::findDeclaration($file->getContents());

                if ($declaration === null) {
                    continue;
                }

                [$classPath, $kind] = $declaration;

                if ($kind === 'enum' && enum_exists($classPath)) {
                    $classes[$classPath] = 'enum';
                } elseif ($kind === 'class' && class_exists($classPath)) {
                    $classes[$classPath] = 'class';
                }
            }
        }

        return $classes;
    }

    /**
     * Finds the first class or enum declared in a PHP file (interfaces, traits and anonymous classes are ignored).
     *
     * @return array{0: class-string, 1: 'class'|'enum'}|null
     */
    public static function findDeclaration(string $code): ?array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            fn (PhpToken $token) => !$token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])
        ));
        $namespace = '';

        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE)) {
                $next = $tokens[$i + 1] ?? null;

                if ($next !== null && $next->is([T_NAME_QUALIFIED, T_STRING])) {
                    $namespace = $next->text;
                }
                continue;
            }

            $kind = match (true) {
                $token->is(T_CLASS) => 'class',
                defined('T_ENUM') && $token->is(T_ENUM) => 'enum',
                default => null,
            };

            if ($kind === null) {
                continue;
            }

            $previous = $tokens[$i - 1] ?? null;
            $name = $tokens[$i + 1] ?? null;

            // Skip "Foo::class" and "new class {}"
            if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
                continue;
            }

            if ($name === null || !$name->is(T_STRING)) {
                continue;
            }

            /** @var class-string $classPath */
            $classPath = ltrim($namespace . '\\' . $name->text, '\\');

            return [$classPath, $kind];
        }

        return null;
    }

    /**
     * @param class-string $classPath
     * @return array<string, mixed>
     */
    private function buildClassResource(string $classPath): array
    {
        $reflectionClass = new ReflectionClass($classPath);
        /** @var ApiResource|null $apiResource */
        $apiResource = ($reflectionClass->getAttributes(ApiResource::class)[0] ?? null)?->newInstance();
        $queries = array_filter($apiResource->queries ?? [], fn ($query) => !$query instanceof QueryCollection);
        $queryCollections = array_filter($apiResource->queries ?? [], fn ($query) => $query instanceof QueryCollection);

        return [
            'type' => 'class',
            'name' => $reflectionClass->getShortName(),
            'queries' => $queries,
            'query_collections' => $queryCollections,
            'mutations' => $apiResource->mutations ?? [],
            'subscriptions' => $apiResource->subscriptions ?? [],
            'reflection' => $reflectionClass,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getResources(string $schemaName, string $type = 'class'): array
    {
        $collection = $this->resources[$schemaName] ?? [];

        return array_filter($collection, fn ($resource) => $resource['type'] === $type);
    }

    /**
     * @return ReflectionClass<object>|ReflectionEnum<\UnitEnum>|null
     * @throws ReflectionException
     */
    public function getReflectionClass(string $schemaName, string $classPath): ReflectionClass|ReflectionEnum|null
    {
        if (!isset($this->resources[$schemaName][$classPath])) {
            return null;
        }

        $resource = &$this->resources[$schemaName][$classPath];

        if ($resource['type'] === 'enum') {
            return $resource['reflection'] ??= new ReflectionEnum($classPath);
        }

        return $resource['reflection'] ??= new ReflectionClass($classPath);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResourceByClassName(string $schemaName, string $className): ?array
    {
        foreach ($this->resources[$schemaName] ?? [] as $classPath => $resource) {
            if ($resource['name'] === $className) {
                $resource['reflection'] = $this->getReflectionClass($schemaName, $classPath);
                return $resource;
            }
        }

        return null;
    }
}
