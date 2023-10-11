# Enums

GraphQL allows to define enums, which are a set of possible values for a given type.
They can be usefull to define a set of possible values for a field, or to define a set of possible values for an argument.

## Usage

There is nothing special to do to use them, you can just define an enum class and use it as a type for a field or an argument.

```php
<?php

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
```

Then it can be used inside the schema. For example, to define on a field level:

```php
<?php

#[ApiResource(...)]
class Article
{
    public StatusEnum $status;
    ...
}
```

Or to define on an argument level:

```php
<?php

#[ApiResource(
    graphqlOperations: [
        new QueryCollection(
            name: 'articlesByStatus',
            args: [
                'status' => ['type' => 'StatusEnum!']
            ]
        )
    ]
)]
class Article
{
    ...
}
```

which will generate the following schema:

```graphql
type Article {
    status: StatusEnum!
    ...
}

type Query {
    articlesByStatus(status: StatusEnum!): [Article!]!
}

enum StatusEnum {
    DRAFT
    PUBLISHED
    ARCHIVED
}
```

The resolver for the query will be passed an instance of the appropriate enum value when invoked.
