<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ResolverProvider
{
    public function __construct(private readonly ServiceLocator $locator, private readonly Serializer $serializer)
    {
    }

    public function getResolver(string $class): QueryItemResolverInterface|QueryCollectionResolverInterface|MutationResolverInterface
    {
        if (!$this->locator->has($class)) {
            throw new \RuntimeException(sprintf('Resolver "%s" not found.', $class));
        }

        return $this->locator->get($class);
    }

    public function getResolveCallback(Attribute $attribute): \Closure
    {
        if ($attribute instanceof Mutation) {
            return function (array $input, $context, ResolveInfo $resolveInfo) use ($attribute) {
                $context = $context ?? [];
                $context['info'] = $resolveInfo;

                $this->serializer->denormalizeUploadedFiles($input);

                if ($attribute->deserialize && $attribute->input) {
                    $input = $this->serializer->denormalize($input, $attribute->input);
                }

                $context['args']['input'] = $input;

                $data = $this->execResolver($attribute, $context);
                return $this->normalizeData($data);
            };
        }

        return function($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute) {
            $context = $context ?? [];
            $context['info'] = $resolveInfo;
            $context['root'] = $root;

            if (!empty($args)) {
                $context['args'] = $args;
            }

            $data = $this->execResolver($attribute, $context);
            $normalizedData = $this->normalizeData($data);

            // TODO: in case of pagination, we extract the total count from the items returned by the resolver
            // TODO: which is bad because it means we have to fetch all the items to get the total count
            // TODO: relay connection will extract a slice of the total, but we could find another way to get the total count and the correct sliced items

            if ($attribute instanceof QueryCollection && $attribute->paginationEnabled) {
                if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR) {
                    $connectionData = Relay::connectionFromArray($normalizedData, $args);
                } elseif ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
                    $connectionData = ['items' => $normalizedData];
                }

                $connectionData['totalCount'] = count($normalizedData);

                return $connectionData;
            }

            return $normalizedData;
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function execResolver(Attribute $attribute, array $context): mixed
    {
        return $this->getResolver($attribute->resolver)($context);
    }

    private function normalizeData(mixed $data): mixed
    {
        if (is_iterable($data)) {
            $normalizedData = [];

            foreach ($data as $item) {
                $normalizedData[] = $this->normalizeData($item);
            }

            return $normalizedData;
        }

        if (is_object($data)) {
            $normalizedData = $this->serializer->normalize($data);
            $className = get_class($data);

            if (str_contains($className, '\\')) {
                $className = substr($className, strrpos($className, '\\') + 1);
            }

            if (isset($normalizedData['id'])) {
                $normalizedData['_id'] = $normalizedData['id'];
                $normalizedData['id'] = Relay::toGlobalId($className, $data->id);
            }

            return $normalizedData;
        }

        return $data;
    }
}
