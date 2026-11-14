<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
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
    /** @var array<string, Schema> */
    private array $schemas = [];

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
     * @param array<string, mixed> $config
     */
    private function buildSchema(string $schemaName, array $config): Schema
    {
        // Extract Query, QueryCollection, Mutation and Subscription attributes from DTO class definitions
        $this->resourceLoader->loadResources($schemaName, $config['resource_directories']);

        $query = $this->buildQueryObject($schemaName);
        $mutation = $this->buildMutationObject($schemaName);
        $subscription = $this->buildSubscriptionObject($schemaName);

        return new Schema([
            'query' => $query,
            'mutation' => $mutation,
            'subscription' => $subscription,
            'typeLoader' => function (string $name) use (&$query, &$mutation, &$subscription, $schemaName) {
                return match ($name) {
                    'Query' => $query,
                    'Mutation' => $mutation,
                    'Subscription' => $subscription,
                    // The type loader must return null for unknown types (e.g. introspection __type(name: "Unknown"))
                    default => $this->typeResolver->findType($name, $schemaName),
                };
            },
        ]);
    }

    private function buildQueryObject(string $schemaName): ObjectType
    {
        $resources = $this->resourceLoader->getResources($schemaName);
        $nodeFields = $this->typeResolver->getNodeFields($schemaName);
        $fields = ['node' => $nodeFields['node']];

        foreach ($resources as $classPath => $resource) {
            /** @var Query[]|QueryCollection[] $queries */
            $queries = array_merge($resource['queries'], $resource['query_collections']);

            foreach ($queries as $query) {
                $isCollection = $query instanceof QueryCollection;
                $operationName = $query->name ?? $this->guessOperationName($resource['name'], $isCollection);

                if (isset($fields[$operationName])) {
                    throw new RuntimeException(sprintf('Duplicate operation name "%s" in Query schema "%s"', $operationName, $schemaName));
                }

                $outputType = $query->output ?? $classPath;

                $fields[$operationName] = $this->fieldsBuilder->getObjectTypeField(
                    $schemaName,
                    $outputType,
                    $query->description,
                    true,
                    $query,
                    $isCollection
                );
            }
        }

        // Plural identifying root field, unless the application already defines a "nodes" query
        $fields['nodes'] ??= $nodeFields['nodes'];

        return new ObjectType([
            'name' => 'Query',
            'fields' => $fields
        ]);
    }

    private function buildMutationObject(string $schemaName): ?ObjectType
    {
        $resources = $this->resourceLoader->getResources($schemaName);
        $fields = [];

        foreach ($resources as $classPath => $resource) {
            /** @var Mutation[] $mutations */
            $mutations = $resource['mutations'];

            foreach ($mutations as $mutation) {
                $operationName = $mutation->name;

                if (isset($fields[$operationName])) {
                    throw new RuntimeException(sprintf('Duplicate operation name "%s" in Mutation schema "%s"', $operationName, $schemaName));
                }

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

                $fields[$operationName] = $mutationData;
            }
        }

        if (empty($fields)) {
            return null;
        }

        return new ObjectType([
            'name' => 'Mutation',
            'fields' => $fields
        ]);
    }

    private function buildSubscriptionObject(string $schemaName): ?ObjectType
    {
        $resources = $this->resourceLoader->getResources($schemaName);
        $fields = [];

        foreach ($resources as $classPath => $resource) {
            /** @var Subscription[] $subscriptions */
            $subscriptions = $resource['subscriptions'];

            foreach ($subscriptions as $subscription) {
                $operationName = $subscription->name;

                if (isset($fields[$operationName])) {
                    throw new RuntimeException(sprintf('Duplicate operation name "%s" in Subscription schema "%s"', $operationName, $schemaName));
                }

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

                $args = [
                    'input' => [
                        'type' => Type::nonNull($inputType),
                    ]
                ];

                $fields[$operationName] = [
                    'type' => $payloadType,
                    'args' => $args,
                    'resolve' => $this->resolverProvider->getResolveCallback($schemaName, $subscription),
                ];
            }
        }

        if (empty($fields)) {
            return null;
        }

        return new ObjectType([
            'name' => 'Subscription',
            'fields' => $fields
        ]);
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
