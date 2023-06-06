![GitHub CI](https://github.com/jav974/JavApiTopiaBundle/actions/workflows/php.yml/badge.svg)
[![codecov](https://codecov.io/gh/jav974/JavApiTopiaBundle/branch/main/graph/badge.svg?token=AIL18WCO85)](https://codecov.io/gh/jav974/JavApiTopiaBundle)

Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require jav/apitopia-bundle
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require jav/apitopia-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Jav\ApiTopiaBundle\JavApiTopiaBundle::class => ['all' => true],
];
```

### Step 3: Configure the Bundle

Import apitopia routing configuration by adding these lines:

```yaml
// config/routes.yaml

apitopia:
    resource: .
    type: apitopia
```
This will import the routes defined in GraphQL endpoints configuration and graphiql endpoint.

### Step 4: Using the Bundle

This bundle provides a set of annotations to define a full GraphQL schema, compliant with [Relay specification](https://relay.dev/docs/guides/graphql-server-specification/)

Annotations provided are:
- Query
- QueryCollection
- Mutation
- SubQuery
- SubQueryCollection
- Subscription

The naming convention and usage of this library is extremely similar to [ApiPlatform](https://api-platform.com/), so if you are familiar with it, you should feel right at home.
Key differences are:

- Multi schemas support, each with their own endpoint
- Full schema definition with PHP attributes, and reflection on DTOs (you may never need to write a custom type)
- Named queries, mutations and subscriptions are not suffixed with the class name (you can call them whatever you want)
- Fully configurable subquery and subquery collection
- Paginated collection resolvers are called with 'limit' and 'offset' arguments computed, even with cursor based pagination type
- No ORM/DataSource integration, you have to provide your own data from the resolvers (but you can use anything you want inside them since they are services)
- No support for REST (yet?), only GraphQL

## GraphQL:

This library supports multiple GraphQL endpoints and schemas. It needs to be defined in the configuration file like so:

```yaml
// config/packages/api_topia.yaml
api_topia:
    schema_output_dir: '%kernel.project_dir%'
    schemas:
        customer: # This is the name of the schema
            path: '/api/graphql/customer' # This is the path of the endpoint
            resource_directories: ['%kernel.project_dir%/src/Api/GraphQL/Customer/DTO'] # This is the directory where the DTOs are located
```

A GraphiQL endpoint is also available at `/api/graphiql/{schema}` where schema is the name of the schema you want to use.

### Queries

You can then define the schema structure by adding DTOs in any of the resource directories specified in config.

```php
// src/Api/GraphQL/Customer/DTO/Product.php
namespace App\Api\GraphQL\Customer\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;

#[ApiResource(
    graphQLOperations: [
        new Query(name: 'product', resolver: ProductResolver::class, output: Product::class, args: ['id' => ['type' => 'ID!']]),
        new QueryCollection(name: 'products', resolver: ProductsResolver::class, output: Product::class),
        ...
    ]
)]
class Product
{
    public int $id;
    public string $name;
    public string $description;
}
```

You can use nested objects in your DTOs:

```php
class Product
{
    public int $id;
    public string $name;
    public string $description;
    public Category $category; // This is a nested object
}
```

Any objects found in the DTOs will be automatically added to the schema. They can be pure object as well as other ApiResources.

The same works for collections:

```php
class Product
{
    public int $id;
    public string $name;
    public string $description;
    /** @var Category[] */
    public array $categories; // This is a collection of nested objects
}
```

When embedding objects or collection in your DTOs, they behave like you would expect from your very own definition of DTO
(you have to provide the data for them in the main DTO resolver).

### Subqueries:

But you can also use subqueries:

```php
class Product
{
    public int $id;
    public string $name;
    public string $description;
    
    #[SubQuery(...)]
    public Category $category; // This is a nested query
    
    #[SubQueryCollection(args: [...], paginationEnabled: true, paginationType: 'cursor')]
    public array $relatedProducts; // This is a nested collection query
}
```

Query and QueryCollection attributes can take an optional `paginationEnabled` argument, which is set to true by default.
When enabled, the query will be paginated using the `paginationType` argument, which can be either 'cursor' or 'offset'.

Cursor based pagination will use relay specification and yield the following structure:

```graphql
type ProductCursorConnection {
    "Information to aid in pagination."
    pageInfo: PageInfo!

    "Information to aid in pagination"
    edges: [ProductEdge]

    "The total count of items in the connection."
    totalCount: Int!
}

type ProductEdge {
    "The item at the end of the edge"
    node: Product

    "A cursor for use in pagination"
    cursor: String!
}

type PageInfo {
    "When paginating forwards, are there more items?"
    hasNextPage: Boolean!

    "When paginating backwards, are there more items?"
    hasPreviousPage: Boolean!

    "When paginating backwards, the cursor to continue."
    startCursor: String

    "When paginating forwards, the cursor to continue."
    endCursor: String
}

type Query {
    products(first: Int, after: String, last: Int, before: String): ProductCursorConnection
}

```

Whereas offset based pagination will yield the following structure:

```graphql
type ProductOffsetConnection {
    "The list of items in the connection."
    items: [Product]

    "The total count of items in the connection."
    totalCount: Int!
}

type Query {
    products(offset: Int, limit: Int): ProductOffsetConnection
}

```

Pagination can also be turned off by setting property `paginationEnabled` of QueryCollection and SubQueryCollection to false.

### Mutations:

Mutations are defined in the same way as queries, but with the `Mutation` attribute:

```php
#[ApiResource(
    graphQLOperations: [
        new Mutation(name: 'createProduct', resolver: CreateProductResolver::class, output: Product::class, args: ['name' => ['type' => 'String!'], 'description' => ['type' => 'String!']]),
        // Or using an input object
        new Mutation(name: 'createProduct', resolver: CreateProductResolver::class, output: Product::class, input: ProductInput::class)
        // Or using an input object within the args:
        new Mutation(name: 'createProduct', resolver: CreateProductResolver::class, output: Product::class, args: ['product' => ['type' => 'ProductInput!']])
    ],
)]
class Product
{
    public int $id;
    public string $name;
    public string $description;
}
```

The output parameter on each of the GraphQL attributes can be omitted to use the same class as the targeted DTO.
It can also be used to return a different object shape if needed.
A `deserialize` parameter is available (defaults to true) to enable or disable deserialization of the input object (when using the `input` property).

### Upload files:

You can define an uploadable field in your DTO like so:

```php
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductInput
{
    public string $name;
    public string $description;
    public ?UploadedFile $image;
}
```

### DateTime:

You can use DateTime objects in your DTOs:

```php
class Product
{
    public string $name;
    public string $description;
    public \DateTime $createdAt; // Can be any instance of \DateTimeInterface
}
```

**/!\ By default DateTime objects are serialized as strings in RFC3339 format (Y-m-d\TH:i:sP). If you want to use a different format, you can use the `Context` attribute:**

```php
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class Product
{
    public string $name;
    public string $description;
    #[Context(
        context: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'],
    )]
    public \DateTime $createdAt; // Can be any instance of \DateTimeInterface
}
```

### Custom resolvers:

Resolvers for graphql operations must implement either:

- `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface` for query operations returning a single item

```php
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;

class ProductResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): Product
    {
        /** @var \GraphQL\Type\Definition\ResolveInfo $info */
        $info = $context['info'];
        $args = $context['args'];
        // ...
    }
}
```

- `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface` for query operations returning a collection

```php
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;

class ProductsResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        /** @var \GraphQL\Type\Definition\ResolveInfo $info */
        $info = $context['info'];
        $args = $context['args'];
        // ...
    }
}
```

- `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface` for mutation operations

```php
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;

class CreateProductResolver implements MutationResolverInterface
{
    public function __invoke(array $context): Product
    {
        $inputArgsOrObject = $context['args']['input'];
        // ...
    }
}
```
