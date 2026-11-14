# Changelog

## 1.1.0 (unreleased)

### Compatibility

- Supports PHP 8.1 to 8.5 and Symfony 6.4, 7.x and 8.x.
- Symfony 5.4 (end of life) and `doctrine/annotations` are no longer supported.
- `ivome/graphql-relay-php` is now required as a stable version (`^0.7`); the `@dev` requirement is no longer needed.
- `ecodev/graphql-upload` 8 is supported.
- `symfony/mercure-bundle` is now optional (only required for subscriptions).
- `phpdocumentor/reflection-docblock` 6 is supported (with Symfony >= 7.0).

### Relay

- Relay is implemented internally: `ivome/graphql-relay-php` (unmaintained since 2023, deprecations on PHP 8.4+) is no longer required. Global IDs and cursors keep the same format, so values already handed to clients keep working. See [docs/relay.md](docs/relay.md).
- Full compliance with the Cursor Connections specification: cursors that do not match an edge are ignored, `hasPreviousPage` is `true` after an `after` cursor and `hasNextPage` is `true` before a `before` cursor (both were always `false`), negative `first`/`last` are rejected.
- `node(id:)` refetches objects automatically with the item query of their type taking an `id` argument; `NodeResolverInterface` remains available for custom logic.
- New plural identifying root field `nodes(ids: [ID!]!): [Node]!` (not added when the application already defines a `nodes` query).
- Reserved type names (`PageInfo`, `*Connection`) are checked in debug mode and by `apitopia:build-schema`.
- **Offset paginated types are renamed from `<Type>OffsetConnection` to `<Type>OffsetPage`**, as names ending with `Connection` are reserved for Relay connections. Set `pagination.offset_type_suffix: OffsetConnection` to keep the old names.
- `Jav\ApiTopiaBundle\GraphQL\Relay\Relay` provides `toGlobalId()`, `fromGlobalId()`, `offsetToCursor()` and `cursorToOffset()`. If your code uses `GraphQLRelay\…` classes directly, switch to it or require `ivome/graphql-relay-php` in your application.
- Introspection of an unknown type (`__type(name: "Unknown")`) returns `null` instead of an error.
- The resource cache is invalidated when the `resource_directories` of a schema change.

### Errors and validation

- Every GraphQL error has a code in `extensions.code` (Apollo conventions: `BAD_USER_INPUT`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `INTERNAL_SERVER_ERROR`, `GRAPHQL_PARSE_FAILED`, `GRAPHQL_VALIDATION_FAILED`). See [docs/errors.md](docs/errors.md).
- New `ClientException` (with `notFound()`, `forbidden()`, `unauthenticated()`), whose message is always exposed, with custom codes and extensions.
- HTTP exceptions, Symfony Security and Validator exceptions are mapped to codes; application exceptions can be mapped with the new `error_mapping` option.
- Mutation inputs are validated with the Symfony Validator when it is installed; violations are returned in `extensions.violations` with their path (e.g. `input.email`). New `Mutation` options: `validate`, `validationGroups`.
- Internal errors are logged (level `error`, channel `apitopia`). They were not logged before.
- A malformed request body returns a 400 with a `BAD_USER_INPUT` error, instead of a 500.

### Performance

- Schemas are built lazily: only the root fields and types used by a request are built. What a schema contains is read from an index cached in the cache directory, built by `cache:warmup` (or on first use) and refreshed in debug mode when a resource directory changes.
- Property information (types, descriptions) is cached outside debug mode, like Symfony's `property_info` service.
- With 200 resources, a request takes about 2.3 ms instead of 10.9 ms (4.7x faster); with the small test schema, 2.1 ms instead of 3.4 ms.

### Security

- Exception messages and stack traces are only exposed when `api_topia.debug` is enabled (defaults to `%kernel.debug%`).
- New limits: `max_query_depth` (default 15), `max_query_complexity` (disabled by default) and `pagination.max_limit` (default 1000).
- Introspection and batching can be disabled (`introspection`, `batching`).
- GraphiQL is only exposed when `graphiql.enabled` is true (defaults to `%kernel.debug%`). Its assets are pinned and protected with Subresource Integrity.
- The GraphQL `Client` no longer sends a hard-coded `XDEBUG_SESSION` cookie (use the new `$defaultHeaders` argument).

### Fixed

- Two deferred fields on the same class no longer share (and mix) their results.
- A deferred `SubQueryCollection` with pagination now returns a connection instead of a raw list.
- A deferred resolver is no longer called again for parents that have no result.
- Deferred buffers are reset after each request (no leak in long-running workers such as FrankenPHP or RoadRunner).
- The Relay `node` field works: `NodeResolverInterface` implementations are autoconfigured and the concrete type is resolved.
- `application/graphql` requests now accept a raw GraphQL document (the legacy JSON payload is still accepted). Variables and operation name may be given in the query string.
- Batched requests containing errors are now detected for the HTTP status code.
- The resource cache is refreshed automatically in debug mode when a resource directory changes.
- Resource discovery uses the PHP tokenizer instead of regular expressions, and two classes producing the same GraphQL type name in a schema now raise an explicit error.
- Sub queries on array parents no longer throw a `TypeError`.
- The GraphQL `Client` now names multipart file parts (`file0`, ...): uploads through a real HTTP client were sent without field name.
- A node that cannot be mapped to a GraphQL type raises an explicit error instead of falling back to a full schema scan.

### Added

- Configuration options: `debug`, `error_http_status_code`, `batching`, `introspection`, `max_query_depth`, `max_query_complexity`, `pagination.default_limit`, `pagination.max_limit`, `graphiql.enabled`.
- `$context['pagination']['fromEnd']` is `true` when only `last` is provided (the offset is then negative, counted from the end of the list).
- `AbstractApiTestCase::$kernelOptions` and `AbstractApiTestCase::$kernelBrowser`.
- `Client` accepts any `HttpClientInterface` (and default headers).

### Changed (behaviour)

- GraphiQL is disabled outside debug mode by default. Set `api_topia.graphiql.enabled: true` to keep it in production.
- Error details are hidden outside debug mode.
- Queries deeper than 15 levels and pages larger than 1000 items are rejected by default. Both limits are configurable.
- Cursor-paginated collections returned as plain arrays now apply `pagination.default_limit` when the client provides neither `first` nor `last` (offset pagination and paginator objects already did).
