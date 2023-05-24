<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Pagination\PaginatorInterface;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ResolverProvider
{
    public function __construct(
        private readonly ServiceLocator $locator,
        private readonly Serializer $serializer
    ) {
    }

    public function getResolver(string $class): QueryItemResolverInterface|QueryCollectionResolverInterface|MutationResolverInterface
    {
        if (!$this->locator->has($class)) {
            throw new \RuntimeException(sprintf('Resolver "%s" not found.', $class));
        }

        return $this->locator->get($class);
    }

    public function getResolveCallback(string $schemaName, Attribute $attribute): Closure
    {
        if ($attribute instanceof Mutation) {
            return function (array $input, $context, ResolveInfo $resolveInfo) use ($attribute, $schemaName) {
                $context = $context ?? [];
                $context['info'] = $resolveInfo;
                $context['schema'] = $schemaName;

                $this->serializer->denormalizeInput($input);

                if ($attribute->deserialize && $attribute->input) {
                    $input = $this->serializer->denormalize($input, $attribute->input);
                }

                $context['args']['input'] = $input;

                return ['data' => $this->execResolver($attribute, $context)];
            };
        }

        return function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute, $schemaName) {
            $isPaginatedCollection = $attribute instanceof QueryCollection && $attribute->paginationEnabled;
            $context = $context ?? [];
            $context += $this->getQueryContext($resolveInfo, $schemaName, $root, $args, $isPaginatedCollection);

            $data = $this->execResolver($attribute, $context);

            if ($isPaginatedCollection) {
                $data = $this->handlePaginatedCollection($attribute, $data, $args, $context);
            }

            return $data;
        };
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getQueryContext(ResolveInfo $resolveInfo, string $schemaName, ?object $root, array $args, bool $isPaginatedCollection): array
    {
        $context = [
            'info' => $resolveInfo,
            'schema' => $schemaName,
        ];

        if ($root) {
            $context['source'] = [
                'item' => $root,
                '#itemResourceClass' => get_class($root),
                '#itemIdentifiers' => [
                    'id' => $root->id ?? null
                ]
            ];
        }

        if (!empty($args)) {
            $context['args'] = $args;
        }

        if ($isPaginatedCollection) {
            $this->toLimits($args, $offset, $limit);

            $context['pagination'] = [
                'offset' => $offset,
                'limit' => $limit
            ];
        }

        return $context;
    }

    /**
     * @param array<object>|PaginatorInterface<object> $data
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function handlePaginatedCollection(QueryCollection $attribute, array|PaginatorInterface $data, array $args, array $context): array
    {
        $connectionData = [];

        if (is_array($data)) {
            if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR) {
                $connectionData = Relay::connectionFromArray($data, $args);
            } elseif ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
                $connectionData = ['items' => array_slice($data, $context['pagination']['offset'], $context['pagination']['limit'])];
            }

            $connectionData['totalCount'] = count($data);
        } elseif ($data instanceof PaginatorInterface) {
            if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR) {
                $connectionData = Relay::connectionFromArraySlice($data->getCurrentPageResults(), $args, [
                    'sliceStart' => $data->getCurrentPageOffset(),
                    'arrayLength' => $data->getTotalItems()
                ]);
            } elseif ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
                $connectionData = ['items' => $data->getCurrentPageResults()];
            }

            $connectionData['totalCount'] = $data->getTotalItems();
        }

        return $connectionData;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function execResolver(Attribute $attribute, array $context): mixed
    {
        return $this->getResolver($attribute->resolver)(context: $context);
    }

    /**
     * Finds the firstResult and maxResults from context query
     * This method can handle cursor based pagination (first, before, last, after)
     * If we'd like to change this navigation to something else, this is the only location where we would operate on the new args
     * No matter what, in the end we just need $firstResult (offset) and $maxResults (limit)
     *
     * @param array<string, mixed> $filters
     */
    private function toLimits(array $filters, ?int &$firstResult = null, ?int &$maxResults = null): void
    {
        $maxResults = $filters['first'] ?? $filters['last'] ?? $filters['limit'] ?? 50;
        $firstResult = $filters['offset'] ?? 0;

        // 'before' and 'after' are integers corresponding to the position of the element in the array
        // They are base64 encoded integer, so we have to decode them in order to compute something

        if (isset($filters['last']) && isset($filters['before'])) {
            $firstResult = ArrayConnection::cursorToOffset($filters['before']) - $filters['last'];
        } elseif (isset($filters['first']) && isset($filters['after'])) {
            $firstResult = ArrayConnection::cursorToOffset($filters['after']) + 1;
        } elseif (isset($filters['last'])) {
            $firstResult = -$filters['last'];
        }
    }
}
