# Deferred Subqueries

Deferred subqueries allow to batch subqueries and execute them in a single Resolver pass.
They are used to solve the N+1 problem that arrises when a subquery is executed for each item in a collection.

## Usage

To use deferred subqueries, you need to enable the option `deferred` in the `SubQuery` or `SubQueryCollection` attribute.
The resolver for a deferred subquery must implement `DeferredResolverInterface`.

```php
<?php

#[ApiResource(...)]
class Taxon
{
    #[\Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection(
        args: ['orderBy' => ['type' => 'String!']],
        resolver: DeferredProductsResolver::class,
        output: Product::class,
        deferred: true
    )]
    public array $products = [];
    
    #[\Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery(
        args: ['period' => ['type' => 'Int!']],
        resolver: DeferredUserResolver::class,
        deferred: true
    )]
    public User $createdBy;
}
```

Now the resolver part:

```php
<?php

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;

class DeferredProductsResolver implements DeferredResolverInterface
{
    public function __invoke(array $context, DeferredResults $results): void
    {
        // $context will contain the parent objects as collection
        $taxons = $context['source']['collection'];
        // And the ids of the parent objects as array
        $taxonIds = $context['source']['#itemIdentifiers']['id'];
    
        $products = $this->productRepository->findByTaxonIds($taxonIds, $context['orderBy']);

        foreach ($products as $product) {
            if (!isset($results[$product->getTaxon()])) {
                $results[$product->getTaxon()] = [];
            }
        
            $results[$product->getTaxon()][] = $product;
        }
    }
}
```