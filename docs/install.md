Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Prerequisites
-------------

- PHP 8.1 or higher
- Symfony 6.4, 7.x or 8.x (Symfony 8 requires PHP 8.4)
- `symfony/mercure-bundle` if you use subscriptions:

```console
$ composer require symfony/mercure-bundle
```

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
#config/routes/apitopia.yaml

apitopia:
  resource: .
  type: apitopia
```
This will import the routes defined in GraphQL endpoints configuration and graphiql endpoint.

Add the following config template:

```yaml
#config/packages/apitopia.yaml

api_topia:
  schema_output_dir: '%kernel.project_dir%'
  schemas:
    default_schema:
      path: '/graphql'
      resource_directories: ['%kernel.project_dir%/src/Api']
```
*Make sure that **resource_directories** exist*


Full configuration reference
----------------------------

```yaml
#config/packages/apitopia.yaml

api_topia:
  schema_output_dir: '%kernel.project_dir%'

  # Expose exception messages and stack traces in GraphQL errors. Never enable this in production.
  debug: '%kernel.debug%'

  # Exceptions exposed to clients with a code (see docs/errors.md)
  error_mapping:
    App\Domain\OrderNotFound: NOT_FOUND

  # HTTP status code of responses containing GraphQL errors.
  # 500 is the historical default; use 200 to follow the GraphQL-over-HTTP convention.
  error_http_status_code: 500

  # Allow several operations in a single HTTP request (JSON array payload)
  batching: true

  # Allow introspection queries (__schema, __type)
  introspection: true

  # Maximum depth of a query (0 disables the check)
  max_query_depth: 15

  # Maximum complexity of a query (0 disables the check)
  max_query_complexity: 0

  pagination:
    # Page size used when the client does not provide "first", "last" or "limit"
    default_limit: 50
    # Maximum page size a client may request (0 disables the cap)
    max_limit: 1000
    # Suffix of offset paginated types ("OffsetConnection" keeps the names of versions < 1.1, see docs/relay.md)
    offset_type_suffix: OffsetPage

  graphiql:
    # Expose the GraphiQL IDE on /api/graphiql/{schema}
    enabled: '%kernel.debug%'

  schemas:
    default_schema:
      path: '/graphql'
      resource_directories: ['%kernel.project_dir%/src/Api']
```

### Recommended production settings

```yaml
#config/packages/prod/apitopia.yaml

api_topia:
  error_http_status_code: 200
  introspection: false
  max_query_complexity: 500
```

Performance
-----------

Schemas are built lazily from an index stored in the cache directory: a request only builds the operations and types it uses. Run `bin/console cache:warmup` when deploying, so that the index is ready before the first request. In debug mode, the index is refreshed automatically when a file of a resource directory changes.

Relay
-----

Schemas follow the Relay server specification (`Node` interface, `node`/`nodes` fields, cursor connections).
See [Relay compliance](relay.md).
