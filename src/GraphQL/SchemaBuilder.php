<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Symfony\Component\Config\ConfigCache;
use Jav\ApiTopiaBundle\GraphQL\Relay\RelayTypes;
use InvalidArgumentException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;
use JsonException;
use LogicException;
use RuntimeException;
use Throwable;

class SchemaBuilder
{
    /** Increase when the structure of the schema index changes */
    private const INDEX_VERSION = 1;

    /** @var array<string, Schema> */
    private array $schemas = [];

    /**
     * @var array<string, array{queries: array<string, array{0: class-string, 1: int}>, mutations: array<string, array{0: class-string, 1: int}>, subscriptions: array<string, array{0: class-string, 1: int}>, nodeQueries: array<string, array{0: class-string, 1: int}>, owners: array<string, string>}>
     */
    private array $indexes = [];

    /** @var array<string, mixed> */
    private array $config;

    private string $schemaOutputDirectory;

    private bool $debug = false;

    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly TypeResolver $typeResolver,
        private readonly ResolverProvider $resolverProvider,
        private readonly FieldsBuilder $fieldsBuilder,
        private readonly TypeRegistry $typeRegistry,
    ) {
    }

    /**
     * In debug mode, schemas are checked against the Relay specification when they are built.
     */
    public function setDebug(bool|string|null $debug): void
    {
        $this->debug = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    public function setSchemaOutputDirectory(string $schemaOutputDirectory): void
    {
        $this->schemaOutputDirectory = $schemaOutputDirectory;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @throws Error|SerializationError|JsonException|Throwable
     */
    public function build(): void
    {
        foreach ($this->config as $schemaName => $config) {
            $this->schemas[$schemaName] = $this->buildSchema($schemaName, $config);
            $this->schemas[$schemaName]->assertValid();
            $this->assertRelayCompliant($this->schemas[$schemaName], $schemaName);

            file_put_contents(
                "$this->schemaOutputDirectory/schema.$schemaName.graphql",
                SchemaPrinter::doPrint($this->schemas[$schemaName])
            );
        }
    }

    public function getSchema(string $schemaName): Schema
    {
        if (isset($this->schemas[$schemaName])) {
            return $this->schemas[$schemaName];
        }

        if (!isset($this->config[$schemaName])) {
            throw new InvalidArgumentException(sprintf('Schema "%s" not found.', $schemaName));
        }

        $schema = $this->buildSchema($schemaName, $this->config[$schemaName]);

        if ($this->debug) {
            $this->assertRelayCompliant($schema, $schemaName);
        }

        return $this->schemas[$schemaName] = $schema;
    }

    /**
     * Checks the type names reserved by the GraphQL Cursor Connections Specification:
     * "PageInfo" and any object whose name ends with "Connection".
     *
     * @throws LogicException
     */
    public function assertRelayCompliant(Schema $schema, string $schemaName): void
    {
        $legacyOffsetSuffix = str_ends_with($this->fieldsBuilder->getOffsetTypeSuffix(), 'Connection')
            ? $this->fieldsBuilder->getOffsetTypeSuffix()
            : null;
        $errors = [];

        foreach ($schema->getTypeMap() as $name => $type) {
            if ($name === 'PageInfo' && $type !== RelayTypes::pageInfo()) {
                $errors[] = 'the type name "PageInfo" is reserved for connections page information';
            }

            if (!str_ends_with($name, 'Connection')) {
                continue;
            }

            // Offset paginated types named with the legacy suffix, explicitly configured
            if ($legacyOffsetSuffix !== null && str_ends_with($name, $legacyOffsetSuffix)) {
                continue;
            }

            if (!$type instanceof ObjectType || !$type->hasField('edges') || !$type->hasField('pageInfo')) {
                $errors[] = sprintf('"%s": type names ending with "Connection" are reserved for connections (objects with "edges" and "pageInfo" fields)', $name);
            }
        }

        if ($errors !== []) {
            throw new LogicException(sprintf('Schema "%s" does not follow the Relay specification: %s.', $schemaName, implode('; ', $errors)));
        }
    }

    /**
     * Builds a lazy schema: root fields and types are only built when a request uses them.
     *
     * What exists in the schema (operations, and what creates each type) is read from an index cached in the
     * cache directory. The index is created by building the whole schema once (cache warmup or first use) and
     * refreshed in debug mode when a resource directory changes.
     *
     * @param array<string, mixed> $config
     */
    private function buildSchema(string $schemaName, array $config): Schema
    {
        $directories = $config['resource_directories'];
        $this->resourceLoader->loadResources($schemaName, $directories);

        $cache = new ConfigCache($this->resourceLoader->getCachePath('schema', $schemaName, $directories, [
            self::INDEX_VERSION,
            $this->fieldsBuilder->getOffsetTypeSuffix(),
        ]), $this->resourceLoader->isDebug());

        if ($cache->isFresh()) {
            $this->indexes[$schemaName] = require $cache->getPath();

            return $this->createSchema($schemaName);
        }

        // Build the whole schema once to know what creates each type
        $this->indexes[$schemaName] = $this->buildIndex($schemaName) + ['owners' => []];
        $schema = $this->createSchema($schemaName);
        $schema->getTypeMap();
        $this->indexes[$schemaName]['owners'] = $this->typeRegistry->getOwners($schemaName);

        $cache->write(
            '<?php return ' . var_export($this->indexes[$schemaName], true) . ';' . PHP_EOL,
            $this->resourceLoader->getCacheResources($directories)
        );

        return $schema;
    }

    /**
     * Lists the operations of a schema. Reflects every resource: only done when the index is (re)built.
     *
     * @return array{queries: array<string, array{0: class-string, 1: int}>, mutations: array<string, array{0: class-string, 1: int}>, subscriptions: array<string, array{0: class-string, 1: int}>, nodeQueries: array<string, array{0: class-string, 1: int}>}
     */
    private function buildIndex(string $schemaName): array
    {
        $index = ['queries' => [], 'mutations' => [], 'subscriptions' => [], 'nodeQueries' => []];
        $reserved = ['query' => ['node' => true], 'mutation' => [], 'subscription' => []];

        foreach ($this->resourceLoader->getResources($schemaName) as $classPath => $resource) {
            // Item queries first, then collections: the field order of previous versions
            /** @var array<int, Query> $queries */
            $queries = $resource['queries'] + $resource['query_collections'];

            foreach ($queries as $position => $query) {
                $isCollection = $query instanceof QueryCollection;
                $name = $query->name ?? $this->guessOperationName($resource['name'], $isCollection);

                if (isset($index['queries'][$name]) || isset($reserved['query'][$name])) {
                    throw new RuntimeException(sprintf('Duplicate operation name "%s" in Query schema "%s"', $name, $schemaName));
                }

                $index['queries'][$name] = [$classPath, $position];
                $outputType = ReflectionUtils::getClassNameFromClassPath($query->output ?? $classPath);

                // The first item query taking an "id" argument refetches nodes of its output type (Relay)
                if (!$isCollection && isset($query->args['id'])) {
                    $index['nodeQueries'][$outputType] ??= [$classPath, $position];
                }
            }

            foreach (['mutations' => 'Mutation', 'subscriptions' => 'Subscription'] as $kind => $label) {
                foreach ($resource[$kind] as $position => $operation) {
                    if (isset($index[$kind][$operation->name])) {
                        throw new RuntimeException(sprintf('Duplicate operation name "%s" in %s schema "%s"', $operation->name, $label, $schemaName));
                    }

                    $index[$kind][$operation->name] = [$classPath, $position];
                }
            }
        }

        return $index;
    }

    private function createSchema(string $schemaName): Schema
    {
        $index = $this->indexes[$schemaName];
        $this->typeResolver->setNodeQueryLocator(function (string $schema, string $typeName) {
            $location = $this->indexes[$schema]['nodeQueries'][$typeName] ?? null;

            return $location !== null ? $this->getOperation($schema, 'queries', $location) : null;
        });

        $query = $this->buildQueryObject($schemaName, $index['queries']);
        $mutation = $index['mutations'] !== [] ? $this->buildMutationObject($schemaName, $index['mutations']) : null;
        $subscription = $index['subscriptions'] !== [] ? $this->buildSubscriptionObject($schemaName, $index['subscriptions']) : null;

        return new Schema([
            'query' => $query,
            'mutation' => $mutation,
            'subscription' => $subscription,
            'typeLoader' => function (string $name) use ($query, $mutation, $subscription, $schemaName) {
                return match ($name) {
                    'Query' => $query,
                    'Mutation' => $mutation,
                    'Subscription' => $subscription,
                    default => $this->loadType($schemaName, $name, $query, $mutation, $subscription),
                };
            },
        ]);
    }

    /**
     * Returns a type of the schema, building what creates it when it has not been built yet.
     * Returns null for unknown types, as required by the type loader contract (e.g. introspection __type(name: "Unknown")).
     *
     * @param array<string, true> $loading Owners being built, against cycles
     */
    private function loadType(string $schemaName, string $name, ObjectType $query, ?ObjectType $mutation, ?ObjectType $subscription, array $loading = []): ?Type
    {
        $type = $this->typeResolver->findType($name, $schemaName);
        $owner = $this->indexes[$schemaName]['owners'][$name] ?? null;

        if ($type !== null || $owner === null || isset($loading[$owner])) {
            return $type;
        }

        $loading[$owner] = true;
        [$kind, $ownerName] = explode(':', $owner, 2);

        if ($kind === 'type') {
            $ownerType = $this->loadType($schemaName, $ownerName, $query, $mutation, $subscription, $loading);

            if ($ownerType instanceof ObjectType || $ownerType instanceof InputObjectType) {
                $ownerType->getFields();
            }
        } else {
            $root = ['query' => $query, 'mutation' => $mutation, 'subscription' => $subscription][$kind] ?? null;
            $root?->findField($ownerName);
        }

        return $this->typeResolver->findType($name, $schemaName);
    }

    /**
     * @param 'queries'|'mutations'|'subscriptions' $kind
     * @param array{0: class-string, 1: int} $location
     */
    private function getOperation(string $schemaName, string $kind, array $location): Query|Mutation|Subscription
    {
        [$classPath, $position] = $location;
        $resource = $this->resourceLoader->getResource($schemaName, $classPath);
        $operations = $kind === 'queries' ? $resource['queries'] + $resource['query_collections'] : $resource[$kind];

        return $operations[$position] ?? throw new LogicException(sprintf('The schema index of "%s" is outdated, clear the cache.', $schemaName));
    }

    /**
     * @param array<string, array{0: class-string, 1: int}> $operations
     */
    private function buildQueryObject(string $schemaName, array $operations): ObjectType
    {
        $nodeFields = $this->typeResolver->getNodeFields($schemaName);
        $fields = ['node' => $nodeFields['node']];

        foreach ($operations as $operationName => $location) {
            $fields[$operationName] = fn () => $this->typeRegistry->withOwner("query:$operationName", function () use ($schemaName, $location) {
                /** @var Query $query */
                $query = $this->getOperation($schemaName, 'queries', $location);

                return $this->fieldsBuilder->getObjectTypeField(
                    $schemaName,
                    $query->output ?? $location[0],
                    $query->description,
                    true,
                    $query,
                    $query instanceof QueryCollection
                );
            });
        }

        // Plural identifying root field, unless the application already defines a "nodes" query
        $fields['nodes'] ??= $nodeFields['nodes'];

        return new ObjectType([
            'name' => 'Query',
            'fields' => $fields
        ]);
    }

    /**
     * @param array<string, array{0: class-string, 1: int}> $operations
     */
    private function buildMutationObject(string $schemaName, array $operations): ObjectType
    {
        $fields = [];

        foreach ($operations as $operationName => $location) {
            $fields[$operationName] = fn () => $this->typeRegistry->withOwner("mutation:$operationName", fn () => $this->buildMutationField(
                $schemaName,
                $operationName,
                $location[0],
                $this->getOperation($schemaName, 'mutations', $location)
            ));
        }

        return new ObjectType([
            'name' => 'Mutation',
            'fields' => $fields
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMutationField(string $schemaName, string $operationName, string $classPath, Mutation $mutation): array
    {
        $outputType = $mutation->output ?? $classPath;
        $fieldName = lcfirst(ReflectionUtils::getClassNameFromClassPath($classPath));
        // An input class replaces the generated input type
        $customInputType = $mutation->input ? $this->typeResolver->resolve($schemaName, $mutation->input, false, true) : null;

        if ($customInputType !== null && !$customInputType instanceof InputObjectType) {
            throw new InvalidArgumentException(sprintf('The input of mutation "%s" must be a class, "%s" given.', $operationName, $mutation->input));
        }

        $mutationData = RelayTypes::mutation(
            $operationName,
            $mutation->input ? null : $this->typeResolver->resolveAttributeArgs($schemaName, $mutation, true),
            [
                $fieldName => [
                    'type' => $this->typeResolver->resolve($schemaName, $outputType, false),
                    'resolve' => fn ($payload) => $payload['data']
                ]
            ],
            $this->resolverProvider->getResolveCallback($schemaName, $mutation),
            $customInputType
        );
        $inputType = $mutationData['inputType'];
        unset($mutationData['inputType']);

        $this->typeRegistry->register("$schemaName.$inputType->name", $inputType);
        $this->typeRegistry->register("$schemaName.{$mutationData['type']->name}", $mutationData['type']);

        return $mutationData;
    }

    /**
     * @param array<string, array{0: class-string, 1: int}> $operations
     */
    private function buildSubscriptionObject(string $schemaName, array $operations): ObjectType
    {
        $fields = [];

        foreach ($operations as $operationName => $location) {
            $fields[$operationName] = fn () => $this->typeRegistry->withOwner("subscription:$operationName", fn () => $this->buildSubscriptionField(
                $schemaName,
                $location[0],
                $this->getOperation($schemaName, 'subscriptions', $location)
            ));
        }

        return new ObjectType([
            'name' => 'Subscription',
            'fields' => $fields
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubscriptionField(string $schemaName, string $classPath, Subscription $subscription): array
    {
        $outputType = $subscription->output ?? $classPath;
        $fieldName = lcfirst(ReflectionUtils::getClassNameFromClassPath($classPath));

        $payloadType = new ObjectType([
            'name' => $subscription->name . 'Payload',
            'fields' => [
                'mercureUrl' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => fn ($payload) => $payload['mercureUrl']
                ],
                'clientSubscriptionId' => [
                    'type' => Type::string(),
                    'resolve' => fn ($payload) => $payload['clientSubscriptionId']
                ],
                $fieldName => [
                    'type' => $this->typeResolver->resolve($schemaName, $outputType, false),
                    'resolve' => fn ($payload) => $payload['data']
                ]
            ]
        ]);

        $this->typeRegistry->register("$schemaName.$payloadType->name", $payloadType);

        $inputType = new InputObjectType([
            'name' => $subscription->name . 'Input',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                ],
                'clientSubscriptionId' => [
                    'type' => Type::string(),
                ],
            ] + $this->typeResolver->resolveAttributeArgs($schemaName, $subscription, true)
        ]);

        $this->typeRegistry->register("$schemaName.$inputType->name", $inputType);

        return [
            'type' => $payloadType,
            'args' => [
                'input' => [
                    'type' => Type::nonNull($inputType),
                ]
            ],
            'resolve' => $this->resolverProvider->getResolveCallback($schemaName, $subscription),
        ];
    }

    private function guessOperationName(string $className, bool $isCollection): string
    {
        $className = lcfirst($className);

        if ($isCollection) {
            $className .= 's';
        }

        return $className;
    }
}
