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
            return function ($input, $context, ResolveInfo $resolveInfo) use ($attribute) {
                $resolver = $this->getResolver($attribute->resolver);
                $context = $context ?? [];
                $context['info'] = $resolveInfo;
                $context['args']['input'] = $input;

                $data = $resolver($context);
                return $this->normalizeData($data);
            };
        }

        return function($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute) {
            $resolver = $this->getResolver($attribute->resolver);
            $context = $context ?? [];
            $context['info'] = $resolveInfo;
            $context['root'] = $root;

            if (!empty($args)) {
                $context['args'] = $args;
            }

            $data = $resolver($context);

            if ($attribute instanceof QueryCollection) {
                $normalizedData = $this->normalizeData($data);
                $connectionData = Relay::connectionFromArray($normalizedData, $args);
                $connectionData['totalCount'] = count($normalizedData);

                return $connectionData;
            }

            return $this->normalizeData($data);
        };
    }

    private function normalizeData($data): array
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
