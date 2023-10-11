## Subqueries

GraphQL allows to expose operations on any object type. Subqueries can be defined in ApiTopia with 2 different attributes:

- SubQuery: This attribute is used to define a subquery on a field.
- SubQueryCollection: This attribute is used to define a subquery on a field that is a collection of object/scalar.

```php
<?php

#[ApiResource(...)]
class User
{
    #[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery(
        name: "userStats",
        args: ['period' => ['type' => 'Int!']],
        resolver: StatResolver::class
    )]
    public Stat $stats = [];

    #[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection(
        name: "posts",
        args: ['orderBy' => ['type' => 'String!']],
        resolver: PostResolver::class,
        output: Post::class,
        paginationEnabled: false
    )]
    public array $posts = [];
}
```

**SubQuery and SubQueryCollection can be used only inside an ApiResource class.**

The above example will produce the following schema:

```graphql
type User {
    id: Int!
    userStats(userId: Int!): Stat!
    posts(userId: Int!): [Post!]!
}

type Stat {
    id: Int!
    ...
}

type Post {
    id: Int!
    ...
}
```

These attributes extend Query and QueryCollection type, so all the options available for queries are also available for subqueries with some exception:
- subqueries don't have a `name` option, instead it is automatically generated from the field name.
- subqueries can be deferred with `deferred` option set to true

