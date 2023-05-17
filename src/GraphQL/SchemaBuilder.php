<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
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
        $fields = [];

        foreach ($resources as $className => $resource) {
            /** @var \ReflectionAttribute[] $queries */
            $attributes = array_merge($resource['queries'], $resource['query_collections']);

            foreach ($attributes as $attribute) {
                /** @var Query|QueryCollection $query */
                $query = $attribute->newInstance();
                $isCollection = $query instanceof QueryCollection;
                $operationName = $query->name ?? $this->guessOperationName($resource['name'], $isCollection);

                if (isset($fields[$operationName])) {
                    throw new \RuntimeException(sprintf('Duplicate operation name "%s" in schema "%s"', $operationName, $schemaName));
                }

                $outputType = $query->output ?? $className;

                $fields[$operationName] = [
                    'type' => $this->typeResolver->resolve($schemaName, $outputType, $isCollection),
                    'args' => $this->typeResolver->resolveAttributeArgs($schemaName, $query),
                    'resolve' => $this->resolverProvider->getResolveCallback($query),
                ];
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
            /** @var \ReflectionAttribute[] $queries */
            $attributes = $resource['mutations'];

            foreach ($attributes as $attribute) {
                /** @var Mutation $mutation */
                $mutation = $attribute->newInstance();
                $operationName = $mutation->name;

                if (isset($fields[$operationName])) {
                    throw new \RuntimeException(sprintf('Duplicate operation name "%s" in schema "%s"', $operationName, $schemaName));
                }

                $outputType = $mutation->output ?? $className;

                if ($mutation->input) {
                    $args = ['input' => ['type' => Type::nonNull($this->typeResolver->resolve($schemaName, $mutation->input, false, true))]];
                } else {
                    $args = ['input' => ['type' => Type::nonNull($this->typeResolver->resolveMutationAttributeArgs($schemaName, $mutation))]];
                }

                $fieldName = lcfirst(substr($className, strrpos($className, '\\') + 1));

                $fields[$operationName] = [
                    'type' => Type::nonNull($this->typeResolver->resolveMutationOutput($schemaName, $fieldName, $outputType, $mutation)),
                    'args' => $args,
                    'resolve' => $this->resolverProvider->getResolveCallback($mutation, $fieldName),
                ];
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
