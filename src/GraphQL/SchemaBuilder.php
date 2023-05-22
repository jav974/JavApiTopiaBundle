<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use GraphQLRelay\Relay;
use InvalidArgumentException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use JsonException;
use RuntimeException;
use Throwable;

class SchemaBuilder
{
    /** @var array<string, Schema> */
    private array $schemas = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $schemaOutputDirectory,
        private readonly ResourceLoader $resourceLoader,
        private readonly TypeResolver $typeResolver,
        private readonly ResolverProvider $resolverProvider
    ) {
    }

    /**
     * @throws Error|SerializationError|JsonException|Throwable
     */
    public function build(): void
    {
        foreach ($this->config as $schemaName => $config) {
            $this->schemas[$schemaName] = $this->buildSchema($schemaName, $config);
            $this->schemas[$schemaName]->assertValid();

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

        return $this->schemas[$schemaName] = $this->buildSchema($schemaName, $this->config[$schemaName]);
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
        $subscription = null;//$this->buildSubscriptionObject($schemaName);

        return new Schema([
            'query' => $query,
            'mutation' => $mutation,
            'subscription' => $subscription,
            'typeLoader' => function (string $name) use (&$query, &$mutation, &$subscription, $schemaName) {
                return match ($name) {
                    'Query' => $query,
                    'Mutation' => $mutation,
                    'Subscription' => $subscription,
                    default => $this->typeResolver->getType($name, $schemaName),
                };
            },
        ]);
    }

    private function buildQueryObject(string $schemaName): ObjectType
    {
        $resources = $this->resourceLoader->getResources($schemaName);
        $fields = [
            'node' => $this->typeResolver->getNodeDefinition()['nodeField']
        ];

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

                $fields[$operationName] = $this->typeResolver->getObjectTypeField(
                    $schemaName,
                    $outputType,
                    $query->description,
                    true,
                    $query,
                    $isCollection
                );
            }
        }

        return new ObjectType([
            'name' => 'Query',
            'fields' => $fields
        ]);
    }

    private function buildMutationObject(string $schemaName): ObjectType
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
                $mutationData = Relay::mutationWithClientMutationId([
                    'name' => $operationName,
                    'inputFields' => $mutation->input ? [] : $this->typeResolver->resolveAttributeArgs($schemaName, $mutation, true),
                    'outputFields' => [
                        $fieldName => [
                            'type' => $this->typeResolver->resolve($schemaName, $outputType, false),
                            'resolve' => fn ($payload) => $payload
                        ]
                    ],
                    'mutateAndGetPayload' => $this->resolverProvider->getResolveCallback($mutation),
                ]);

                if ($mutation->input) {
                    $mutationData['args']['input']['type'] = Type::nonNull($this->typeResolver->resolve($schemaName, $mutation->input, false, true));
                }

                /** @var Type&NullableType $inputType */
                $inputType = $mutationData['args']['input']['type']->getWrappedType();

                if (!isset($inputType->name)) {
                    throw new InvalidArgumentException("No name found for input type");
                }

                $this->typeResolver->setType($schemaName, $inputType->name, $inputType);
                $this->typeResolver->setType($schemaName, $mutationData['type']->name, $mutationData['type']);

                $fields[$operationName] = $mutationData;
            }
        }

        return new ObjectType([
            'name' => 'Mutation',
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
