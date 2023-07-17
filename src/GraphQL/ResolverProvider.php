<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;
use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Pagination\PaginatorInterface;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ResolverProvider
{
    /** @var array<string, DeferredResults> */
    private array $deferredBuffer = [];

    public function __construct(
        private readonly ServiceLocator $locator,
        private readonly Serializer $serializer,
        private readonly MercureUrlGenerator $mercureUrlGenerator,
    ) {
    }

    public function getResolver(string $class): QueryItemResolverInterface|QueryCollectionResolverInterface|MutationResolverInterface
    {
        if (!$this->locator->has($class)) {
            throw new RuntimeException(sprintf('Resolver "%s" not found.', $class));
        }

        $resolver = $this->locator->get($class);

        if ($resolver instanceof DeferredResolverInterface) {
            throw new RuntimeException(sprintf('Resolver "%s" is a DeferredResolverInterface.', $class));
        }

        return $resolver;
    }

    public function getDeferredResolver(string $class): DeferredResolverInterface
    {
        if (!$this->locator->has($class)) {
            throw new RuntimeException(sprintf('Resolver "%s" not found.', $class));
        }

        $resolver = $this->locator->get($class);

        if (!$resolver instanceof DeferredResolverInterface) {
            throw new RuntimeException(sprintf('Resolver "%s" is not a DeferredResolverInterface.', $class));
        }

        return $resolver;
    }

    public function getResolveCallback(string $schemaName, Attribute $attribute): Closure
    {
        if ($attribute instanceof Mutation) {
            return function (array $input, $context, ResolveInfo $resolveInfo) use ($attribute, $schemaName) {
                $context = $context ?? [];
                $context['info'] = $resolveInfo;
                $context['schema'] = $schemaName;

                if ($attribute->deserialize && $attribute->input) {
                    $input = $this->serializer->denormalize($input, $attribute->input);
                } else {
                    // Transforms Psr7 UploadedFile to Symfony UploadedFile
                    $this->serializer->denormalizeInput($input);
                }

                $context['args']['input'] = $input;

                return ['data' => $this->execResolver($attribute, $context)];
            };
        } elseif ($attribute instanceof Subscription) {
            return function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute, $schemaName) {
                $context = $context ?? [];
                $context['info'] = $resolveInfo;
                $context['schema'] = $schemaName;

                $data = !empty($attribute->resolver) ? $this->execResolver($attribute, $context) : null;

                return [
                    'data' => $data,
                    'mercureUrl' => $this->mercureUrlGenerator->generate($args['input']['id'], []),
                    'clientSubscriptionId' => $args['input']['clientSubscriptionId'] ?? null,
                ];
            };
        }

        return function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute, $schemaName) {
            $isPaginatedCollection = $attribute instanceof QueryCollection && $attribute->paginationEnabled;
            $isDeferred = ($attribute instanceof SubQuery || $attribute instanceof SubQueryCollection) && $attribute->deferred;
            $context = $context ?? [];
            $context += $this->getQueryContext($resolveInfo, $schemaName, $root, $args, $isPaginatedCollection);

            if ($isDeferred && $root) {
                $rootClass = get_class($root);
                $this->deferredBuffer[$rootClass] = $this->deferredBuffer[$rootClass] ?? new DeferredResults();
                $this->deferredBuffer[$rootClass][$root] = $this->deferredBuffer[$rootClass][$root] ?? null;

                return new Deferred(function () use ($attribute, $context, $rootClass, $root) {
                    $unresolved = $this->deferredBuffer[$rootClass]->getUnresolvedParents();

                    if (!empty($unresolved)) {
                        $context['source'] = [
                            'collection' => $unresolved,
                            '#itemResourceClass' => $rootClass,
                            '#itemIdentifiers' => [
                                'id' => array_map(fn (object $object) => $object->id ?? null, $unresolved),
                            ]
                        ];

                        $this->execResolver($attribute, $context, $this->deferredBuffer[$rootClass]);
                    }

                    return $this->deferredBuffer[$rootClass][$root];
                });
            }

            $data = $this->execResolver($attribute, $context);

            if ($isPaginatedCollection && $attribute instanceof QueryCollection) {
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
    private function execResolver(Attribute $attribute, array $context, ?DeferredResults $results = null): mixed
    {
        if ($attribute->resolver === null) {
            throw new RuntimeException(sprintf('Resolver for attribute "%s" cannot be null', $attribute->name));
        }

        if ($results !== null) {
            $resolver = $this->getDeferredResolver($attribute->resolver);
            $resolver(context: $context, results: $results);
            return null;
        } else {
            $resolver = $this->getResolver($attribute->resolver);
            return $resolver(context: $context);
        }
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
            $firstResult = max(ArrayConnection::cursorToOffset($filters['before']) - $filters['last'], 0);
        } elseif (isset($filters['first']) && isset($filters['after'])) {
            $firstResult = ArrayConnection::cursorToOffset($filters['after']) + 1;
        } elseif (isset($filters['last'])) {
            $firstResult = -$filters['last'];
        }
    }
}
