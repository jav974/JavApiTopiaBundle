# Relay compliance

ApiTopia implements the [GraphQL Relay server specification](https://relay.dev/docs/guides/graphql-server-specification/):
[Global Object Identification](https://relay.dev/graphql/objectidentification.htm) and
[Cursor Connections](https://relay.dev/graphql/connections.htm). No third party Relay library is needed.

## Object identification

Every class with an `#[ApiResource]` attribute implements the `Node` interface:

- its `id` field is a global ID, `base64("<schema>.<Type>:<id>")`;
- its own `id` property is exposed as `_id`.

Each schema exposes:

- `node(id: ID!): Node`: refetches any object from its global ID.
- `nodes(ids: [ID!]!): [Node]!`: refetches several objects at once. The list has the same length and order as `ids`, with `null` for the objects that cannot be fetched. This field is not added if you define your own `nodes` query.

### Refetching

By default, an object is refetched with the **item `Query` of its type that takes an `id` argument**. For example:

```php
#[ApiResource(queries: [
    new Query(resolver: UserResolver::class, args: ['id' => ['type' => 'Int!']]),
])]
class User { /* ... */ }
```

Here `node(id: "<global id of User 42>")` calls `UserResolver` with `$context['args']['id'] === 42`, so it returns the same object as `user(id: 42)`, as the specification requires.

When there is no such query, or when you need custom logic, implement `NodeResolverInterface`. It is autoconfigured, and it replaces the automatic refetch:

```php
use Jav\ApiTopiaBundle\GraphQL\NodeResolverInterface;

final class NodeResolver implements NodeResolverInterface
{
    // $type is "<schema>.<Type>", $id the raw identifier
    public function resolve(string $type, string $id): mixed
    {
        return match ($type) {
            'default_schema.User' => $this->users->find($id),
            default => null,
        };
    }
}
```

Return an object whose short class name is a GraphQL type of the schema, an array with a `__typename` key, or `null` when the object cannot be fetched.

### Global IDs in your code

```php
use Jav\ApiTopiaBundle\GraphQL\Relay\Relay;

$globalId = Relay::toGlobalId('default_schema.User', 42);
['type' => $type, 'id' => $id] = Relay::fromGlobalId($globalId); // null if invalid
```

## Connections

Cursor paginated collections (`QueryCollection` and `SubQueryCollection`, `paginationType: 'cursor'`, the default) are exposed as `<Type>CursorConnection` with `edges { cursor node }`, `pageInfo` and `totalCount`. They accept `first`, `after`, `last` and `before`.

The [pagination algorithm](https://relay.dev/graphql/connections.htm#sec-Pagination-algorithm) is followed exactly:

- `first` and `last` must be non-negative, otherwise an error is returned.
- A cursor that does not match any edge (unknown or malformed) is ignored.
- `hasNextPage` is `true` when there are more edges after the page when paginating forwards. When paginating backwards with `before`, it is `true` because the `before` edge follows the page.
- `hasPreviousPage` follows the same rules in the other direction (`last`, or `after`).
- `startCursor` and `endCursor` are the cursors of the first and last edges, or `null` without edges.

Two server choices on top of the specification:

- When neither `first` nor `last` is given, `pagination.default_limit` is used as an implicit `first`, and `pageInfo` reflects it.
- `first` and `last` are capped by `pagination.max_limit`.

### Resolvers

Resolvers receive the page to load in `$context['pagination']`:

| Arguments | `offset` | `limit` |
|---|---|---|
| `first: 10` | `0` | `10` |
| `first: 10, after: <cursor of item 4>` | `5` | `10` |
| `last: 10, before: <cursor of item 30>` | `20` | `10` |
| `last: 10` | `-10` (`fromEnd: true`) | `10` |

A negative offset counts from the end of the list, like `array_slice()`, because the total count is not known yet.

A resolver can return:

- the whole list (an array or any iterable): the connection is built from it;
- a `PaginatorInterface` (`ArrayPaginator`, `VirtualArrayPaginator` or your own) holding the requested page and the total count.

If a client sends a cursor that does not exist (e.g. after the list shrank), the requested page differs from the computed one. The resolver is then called a second time with the right `$context['pagination']`.

Cursors are opaque to clients. Their format, `base64("arrayconnection:<offset>")`, is the one of the Relay reference implementation, so cursors issued by previous versions keep working.

## Reserved type names

The specification reserves `PageInfo` and every object type whose name ends with `Connection`. In debug mode and in `apitopia:build-schema`, schemas are checked and an explicit error is raised when a class would use one of these names.

Offset paginated collections are therefore named `<Type>OffsetPage` (they were `<Type>OffsetConnection` before 1.1). To keep the old names for existing clients:

```yaml
api_topia:
  pagination:
    offset_type_suffix: OffsetConnection
```

## Mutations

Mutations follow the Relay input object convention: a single `input: <Name>Input!` argument and a `<Name>Payload` result, both with an optional `clientMutationId` that is echoed back.
