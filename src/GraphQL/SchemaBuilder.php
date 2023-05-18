<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;

class SchemaBuilder
{
    /** @var Schema[] */
    private array $schemas = [];

    public function __construct(
        private readonly array $config,
        private readonly string $schemaOutputDirectory,
        private readonly ResourceLoader $resourceLoader,
        private readonly TypeResolver $typeResolver,
        private readonly ResolverProvider $resolverProvider
    ) {
    }

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
            throw new \InvalidArgumentException(sprintf('Schema "%s" not found.', $schemaName));
        }

        return $this->schemas[$schemaName] = $this->buildSchema($schemaName, $this->config[$schemaName]);
    }

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
        $resources = $this->resourceLoader->getResources($schemaName) ?? [];
        $fields = [
            'node' => $this->typeResolver->getNodeDefinition()['nodeField']
        ];

        foreach ($resources as $className => $resource) {
            /** @var Query[]|QueryCollection[] $queries */
            $queries = array_merge($resource['queries'], $resource['query_collections']);

            foreach ($queries as $query) {
                $isCollection = $query instanceof QueryCollection;
                $operationName = $query->name ?? $this->guessOperationName($resource['name'], $isCollection);

                if (isset($fields[$operationName])) {
                    throw new \RuntimeException(sprintf('Duplicate operation name "%s" in schema "%s"', $operationName, $schemaName));
                }

                $outputType = $query->output ?? $className;

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
        $resources = $this->resourceLoader->getResources($schemaName) ?? [];
        $fields = [];

        foreach ($resources as $className => $resource) {
            /** @var Mutation[] $mutations */
            $mutations = $resource['mutations'];

            foreach ($mutations as $mutation) {
                $operationName = $mutation->name;

                if (isset($fields[$operationName])) {
                    throw new \RuntimeException(sprintf('Duplicate operation name "%s" in schema "%s"', $operationName, $schemaName));
                }

                $outputType = $mutation->output ?? $className;
                $inputType = null;

                if ($mutation->input) {
                    $inputType = Type::nonNull($this->typeResolver->resolve($schemaName, $mutation->input, false, true));
                    $args = [];
                } else {
                    $args = $this->typeResolver->resolveAttributeArgs($schemaName, $mutation, true);
                }

                if (str_contains($className, '\\')) {
                    $className = substr($className, strrpos($className, '\\') + 1);
                }

                $fieldName = lcfirst($className);

                $mutationData = Relay::mutationWithClientMutationId([
                    'name' => $operationName,
                    'inputFields' => $args,
                    'outputFields' => [
                        $fieldName => [
                            'type' => $this->typeResolver->resolve($schemaName, $outputType, false),
                            'resolve' => fn ($payload) => $payload
                        ]
                    ],
                    'mutateAndGetPayload' => $this->resolverProvider->getResolveCallback($mutation),
                ]);

                if ($inputType) {
                    $mutationData['args']['input']['type'] = $inputType;
                }

                /** @var NonNull $inputType */
                $inputType = $mutationData['args']['input']['type'];
                $inputType = $inputType->getWrappedType();

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
