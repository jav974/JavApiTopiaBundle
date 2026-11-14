# Changelog

## 1.1.0 (unreleased)

### Compatibility

- Supports PHP 8.1 to 8.5 and Symfony 6.4, 7.x and 8.x.
- Symfony 5.4 (end of life) and `doctrine/annotations` are no longer supported.
- `ivome/graphql-relay-php` is now required as a stable version (`^0.7`); the `@dev` requirement is no longer needed.
- `ecodev/graphql-upload` 8 is supported.
- `symfony/mercure-bundle` is now optional (only required for subscriptions).

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

### Added

- Configuration options: `debug`, `error_http_status_code`, `batching`, `introspection`, `max_query_depth`, `max_query_complexity`, `pagination.default_limit`, `pagination.max_limit`, `graphiql.enabled`.
- `$context['pagination']['fromEnd']` is `true` when only `last` is provided (the offset is then negative, counted from the end of the list).
- `AbstractApiTestCase::$kernelOptions` and `AbstractApiTestCase::$kernelBrowser`.

### Changed (behaviour)

- GraphiQL is disabled outside debug mode by default. Set `api_topia.graphiql.enabled: true` to keep it in production.
- Error details are hidden outside debug mode.
- Queries deeper than 15 levels and pages larger than 1000 items are rejected by default. Both limits are configurable.
- Cursor-paginated collections returned as plain arrays now apply `pagination.default_limit` when the client provides neither `first` nor `last` (offset pagination and paginator objects already did).
