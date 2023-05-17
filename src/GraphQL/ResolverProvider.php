<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ResolverProvider
{
    public function __construct(private readonly ServiceLocator $locator)
    {
    }

    public function getResolver(string $class): QueryItemResolverInterface|QueryCollectionResolverInterface|MutationResolverInterface
    {
        if (!$this->locator->has($class)) {
            throw new \RuntimeException(sprintf('Resolver "%s" not found.', $class));
        }

        return $this->locator->get($class);
    }

    public function getResolveCallback(Attribute $attribute, ?string $wrapUnderName = null): \Closure
    {
        return function($root, array $args, $context, ResolveInfo $resolveInfo) use ($attribute, $wrapUnderName) {
            $resolver = $this->getResolver($attribute->resolver);
            $context = $context ?? [];
            $context['info'] = $resolveInfo;
            $context['root'] = $root;

            if (!empty($args)) {
                $context['args'] = $args;
            }

            $data = $resolver($context);

            if (!empty($wrapUnderName)) {
                return [$wrapUnderName => $data];
            }

            return $data;
        };
    }
}
