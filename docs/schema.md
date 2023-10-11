# Schema definition

To start defining your schema, you will need to write plain php classes in the directory specified
in the configuration file for this schema (`resource_directories`)
The classes should contain only public attributes or have a public getter for any protected/private attribute.

During schema generation, the classes will be scanned and the attributes they contain will be used to generate the schema.

## ApiResource or simple object ?

If you want to expose your class as a resource, you will need to add the `ApiResource` attribute to it.
ApiResources are used to expose queries, mutations or subscriptions to the dedicated Query/Mutation/Subscription types.
They also follow the relay specification for Node types (https://relay.dev/docs/guides/graphql-server-specification/).

**Having an ApiResource in your schema will result in the generation of type implementing the Node interface and allow you
to specify operations for it:**

```php

#[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource(
    graphQLOperations: [...]
)]
class User
{
    public string $id;
    public ?string $name;
    public string $email;
}
```

The above example will produce the following schema:

```graphql
interface Node {
    id: ID!
}

type User implements Node {
    id: ID! # This is the id field that every Node type must have (see https://relay.dev/docs/guides/graphql-server-specification/)
    _id: String! # This *new* property is the same type and will contain the exact value as the $id field defined in the DTO
    name: String
    email: String!
}
```

It is however possible to define a class that is not an ApiResource. This will result in the generation of a simple object type:

```php
class User
{
    public string $id;
    public ?string $name;
    public string $email;
}
```

The above example will produce the following schema*:

```graphql
type User {
    id: String!
    name: String
    email: String!
}
```

The limitation here is that it will not be possible to expose operations on this type.

_*The type will be exposed only if it is referenced by an ApiResource or another exposed type._

## Field types

The type of field exposed in the schema is determined with (by order of priority):
- PHP Reflection on the field
- PHP DocBlock on the field

When specifying a collection type (php array or iterable), the item type has to be specified in the docblock:

```php
class User
{
    /** @var string[] */
    public array $roles;
}
```

## Exposing queries

Queries are exposed by adding the `Query` attribute to an ApiResource class:

```php
#[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource(
    graphQLOperations: [
        new Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query(
            name: "user",
            args: ['id' => ['type' => 'Int!']],
            resolver: UserResolver::class
        )
    ]
)]
class User
{
    ...
}
```

```graphql
type User implements Node {
    ...
}

type Query {
    user(id: Int!): User
}
```

#### Query arguments

- `name`: The name of the query in the schema (if left empty, the resource name will be used in camelCase)
- `args`: An array of arguments for the query. The key is the name of the argument and the value is an array containing the following keys:
    - `type`: The type of the argument. Can be a scalar type, a custom type or a type defined in the schema (see below)
    - `defaultValue`: The default value for the argument
    - `description`: The description of the argument
- `resolver`: The FQCN that will resolve the query. It must implement the `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface` interface.
- `description`: The description of the query (optional)
- `output`: The FQCN of the type that will be returned by the query. If not specified, the type will be inferred from the class this attribute is found on.

## Exposing query collections

Query collections are exposed by adding the `QueryCollection` attribute to an ApiResource class:

```php
#[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource(
    graphQLOperations: [
        new Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection(
            name: "users",
            args: ['active' => ['type' => 'Boolean!']],
            resolver: UserCollectionResolver::class
        )
    ]
)]
class User
{
    ...
}
```

```graphql
type User implements Node {
    ...
}

type Query {
    users(active: Boolean!): [User!]
}
```

#### Query collection arguments

- `name`: The name of the query collection in the schema (if left empty, the resource name will be used in camelCase)
- `args`: An array of arguments for the query collection. The key is the name of the argument and the value is an array containing the following keys:
    - `type`: The type of the argument. Can be a scalar type, a custom type or a type defined in the schema (see below)
    - `defaultValue`: The default value for the argument
    - `description`: The description of the argument
- `resolver`: The FQCN that will resolve the query collection. It must implement the `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface` interface.
- `description`: The description of the query collection (optional)
- `paginationEnabled`: Wether or not the query collection should be paginated (default: true)
- `paginationType`: The type of pagination to use (either 'offset' or 'cursor', default: 'cursor')
- `output`: The FQCN of the type that will be returned by the query collection. If not specified, the type will be inferred from the class this attribute is found on.

## Exposing mutations

Mutations are exposed by adding the `Mutation` attribute to an ApiResource class:

```php
#[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource(
    graphQLOperations: [
        new Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation(
            name: "createUser",
            input: CreateUserInput::class,
            resolver: CreateUserResolver::class
        )
    ]
)]
class User
{
    ...
}
```

```graphql
type User implements Node {
    ...
}

type CreateUserInput {
    ...
}

type createUserPayload {
    user: User
    clientMutationId: String
}

type Mutation {
    createUser(input: CreateUserInput!): createUserPayload
}
```

or with args instead:

```php
#[Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource(
    graphQLOperations: [
        new Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation(
            name: "createUser",
            args: ['objectInput' => ['type' => 'CreateUserInput!']],
            resolver: CreateUserResolver::class
        )
    ]
)]
class User
{
    ...
}
```

```graphql
type User implements Node {
    ...
}

type CreateUserInput {
    ...
}

type createUserInput {
    objectInput: CreateUserInput!
    clientMutationId: String
}

type createUserPayload {
    user: User
    clientMutationId: String
}

type Mutation {
    createUser(input: createUserInput!): createUserPayload
}
```

#### Mutation arguments

- `name`: The name of the mutation in the schema (mandatory)
- `input`: The FQCN of the input type for the mutation (optional)
- `resolver`: The FQCN that will resolve the mutation. It must implement the `Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface` interface.
- `description`: The description of the mutation (optional)
- `args`: An array of arguments for the mutation. The key is the name of the argument and the value is an array containing the following keys:
    - `type`: The type of the argument. Can be a scalar type, a custom type or a type defined in the schema (see below)
    - `defaultValue`: The default value for the argument
    - `description`: The description of the argument
- `output`: The FQCN of the type that will be returned by the mutation. If not specified, the type will be inferred from the class this attribute is found on.
- `deserialize`: Wether or not the input should be deserialized before being passed to the resolver (default: true)

