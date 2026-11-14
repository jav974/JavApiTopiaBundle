<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Closure;
use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use Jav\ApiTopiaBundle\GraphQL\Relay\ConnectionArguments;
use Jav\ApiTopiaBundle\GraphQL\Relay\ConnectionBuilder;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;
use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Error\ValidationException;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Pagination\PaginatorInterface;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ResolverProvider
{
    /** @var array<string, DeferredResults> Deferred results, indexed by field (parent type, field name and arguments) */
    private array $deferredBuffer = [];

    private ?ValidatorInterface $validator = null;

    private int $defaultLimit = 50;
    private int $maxLimit = 0;

    /**
     * @param ServiceLocator<mixed> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator,
        private readonly Serializer $serializer,
        private readonly MercureUrlGenerator $mercureUrlGenerator,
    ) {
    }

    /**
     * @param array{default_limit?: int, max_limit?: int} $options
     */
    public function setPaginationOptions(array $options): void
    {
        $this->defaultLimit = max(1, (int) ($options['default_limit'] ?? $this->defaultLimit));
        $this->maxLimit = max(0, (int) ($options['max_limit'] ?? $this->maxLimit));
    }

    /**
     * Enables the validation of deserialized mutation inputs.
     */
    public function setValidator(?ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * Clears the deferred buffers. Called at the end of each request so that state never leaks between requests.
     */
    public function reset(): void
    {
        $this->deferredBuffer = [];
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
                    $this->validate($attribute, $input);
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
            $isDeferred = $isDeferred === true;
            $context = $context ?? [];
            $isCursorPagination = $isPaginatedCollection && $attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR;
            $context += $this->getQueryContext($resolveInfo, $schemaName, $root, $args, $isPaginatedCollection, $isCursorPagination);

            if ($isDeferred) {
                if (!is_object($root)) {
                    throw new RuntimeException(sprintf('Deferred resolver "%s" requires its parent to be an object, got %s.', $attribute->resolver, get_debug_type($root)));
                }

                $bufferKey = $this->getDeferredBufferKey($resolveInfo, $args);
                $buffer = $this->deferredBuffer[$bufferKey] ??= new DeferredResults();
                $buffer->register($root);

                return new Deferred(function () use ($attribute, $context, $buffer, $root, $args, $isPaginatedCollection) {
                    $unresolved = $buffer->getUnresolvedParents();

                    if (!empty($unresolved)) {
                        $context['source'] = [
                            'collection' => $unresolved,
                            '#itemResourceClass' => get_class($root),
                            '#itemIdentifiers' => [
                                'id' => array_map(fn (object $object) => $object->id ?? null, $unresolved),
                            ]
                        ];

                        $this->execResolver($attribute, $context, $buffer);
                        $buffer->markResolved($unresolved);
                    }

                    $data = $buffer[$root];

                    if ($isPaginatedCollection) {
                        /** @var QueryCollection $attribute */
                        $data = $this->handlePaginatedCollection($attribute, is_iterable($data) ? $data : [], $args, $context);
                    }

                    return $data;
                });
            }

            $data = $this->execResolver($attribute, $context);

            if ($isPaginatedCollection) {
                /** @var QueryCollection $attribute */
                $data = $this->handlePaginatedCollection($attribute, $data, $args, $context, function (array $window) use ($attribute, $context) {
                    // The requested page did not match the list (e.g. a cursor of a removed item): load the right one
                    $context['pagination'] = $window + ['fromEnd' => false];

                    return $this->execResolver($attribute, $context);
                });
            }

            return $data;
        };
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getQueryContext(ResolveInfo $resolveInfo, string $schemaName, mixed $root, array $args, bool $isPaginatedCollection, bool $isCursorPagination = false): array
    {
        $context = [
            'info' => $resolveInfo,
            'schema' => $schemaName,
        ];

        if (is_object($root)) {
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
            ['offset' => $offset, 'limit' => $limit] = $isCursorPagination
                ? $this->getConnectionArguments($args)->window()
                : $this->getOffsetWindow($args);

            $context['pagination'] = [
                'offset' => $offset,
                'limit' => $limit,
                // A negative offset means "count from the end of the list" (array_slice semantics), when only 'last' is provided
                'fromEnd' => $offset < 0,
            ];
        }

        return $context;
    }

    /**
     * @param iterable<mixed>|PaginatorInterface|null $data
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @param (callable(array{offset: int, limit: int}): mixed)|null $reload Loads another page of the list
     * @return array<string, mixed>
     */
    private function handlePaginatedCollection(QueryCollection $attribute, iterable|PaginatorInterface|null $data, array $args, array $context, ?callable $reload = null): array
    {
        $data = $this->normalizeCollection($data);

        if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
            return [
                'items' => is_array($data)
                    ? array_slice($data, $context['pagination']['offset'], $context['pagination']['limit'])
                    : $data->getCurrentPageResults(),
                'totalCount' => is_array($data) ? count($data) : $data->getTotalItems(),
            ];
        }

        $connectionArguments = $this->getConnectionArguments($args);
        $connection = $this->buildConnection($data, $connectionArguments);

        if ($connection === null && $reload !== null) {
            $connection = $this->buildConnection(
                $this->normalizeCollection($reload(ConnectionBuilder::requiredWindow($data instanceof PaginatorInterface ? $data->getTotalItems() : count($data), $connectionArguments))),
                $connectionArguments
            );
        }

        return $connection ?? throw new RuntimeException(sprintf(
            'The paginator returned by resolver "%s" does not contain the requested page: use $context[\'pagination\'] to load it.',
            $attribute->resolver
        ));
    }

    /**
     * @param array<mixed>|PaginatorInterface $data
     * @return array<string, mixed>|null null when a paginator does not contain the requested page
     */
    private function buildConnection(array|PaginatorInterface $data, ConnectionArguments $arguments): ?array
    {
        if (is_array($data)) {
            return ConnectionBuilder::fromArray($data, $arguments) + ['totalCount' => count($data)];
        }

        $connection = ConnectionBuilder::fromSlice($data->getCurrentPageResults(), $data->getCurrentPageOffset(), $data->getTotalItems(), $arguments);

        return $connection === null ? null : $connection + ['totalCount' => $data->getTotalItems()];
    }

    /**
     * @param iterable<mixed>|PaginatorInterface|null $data
     * @return array<mixed>|PaginatorInterface
     */
    private function normalizeCollection(mixed $data): array|PaginatorInterface
    {
        if ($data instanceof PaginatorInterface || is_array($data)) {
            return $data;
        }

        return $data instanceof \Traversable ? iterator_to_array($data, false) : [];
    }

    /**
     * @throws ValidationException
     */
    private function validate(Mutation $mutation, mixed $input): void
    {
        if ($this->validator === null || !$mutation->validate || !is_object($input)) {
            return;
        }

        $violations = $this->validator->validate($input, null, $mutation->validationGroups);

        if ($violations->count() > 0) {
            throw new ValidationException($violations, 'input');
        }
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
     * @param array<string, mixed> $args
     * @throws UserError
     */
    private function getConnectionArguments(array $args): ConnectionArguments
    {
        return ConnectionArguments::fromArgs($args, $this->defaultLimit, $this->maxLimit);
    }

    /**
     * @param array<string, mixed> $args
     * @return array{offset: int, limit: int}
     * @throws UserError
     */
    private function getOffsetWindow(array $args): array
    {
        $limit = (int) ($args['limit'] ?? $this->defaultLimit);

        if ($limit < 0 || (int) ($args['offset'] ?? 0) < 0) {
            throw new UserError('Arguments "offset" and "limit" must be non-negative integers.');
        }

        if ($this->maxLimit > 0 && $limit > $this->maxLimit) {
            throw new UserError(sprintf('Cannot request more than %d items per page.', $this->maxLimit));
        }

        return ['offset' => (int) ($args['offset'] ?? 0), 'limit' => $limit];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function getDeferredBufferKey(ResolveInfo $resolveInfo, array $args): string
    {
        return $resolveInfo->parentType->name . '.' . $resolveInfo->fieldName . '#' . md5(serialize($args));
    }
}
