![GitHub CI](https://github.com/jav974/JavApiTopiaBundle/actions/workflows/php.yml/badge.svg)
[![codecov](https://codecov.io/gh/jav974/JavApiTopiaBundle/branch/main/graph/badge.svg?token=AIL18WCO85)](https://codecov.io/gh/jav974/JavApiTopiaBundle)
![Scrutinizer code quality (GitHub/Bitbucket)](https://img.shields.io/scrutinizer/quality/g/jav974/JavApiTopiaBundle)
[![PHP Version Require](http://poser.pugx.org/phpunit/phpunit/require/php)](https://packagist.org/packages/phpunit/phpunit)
![phpstan level](https://img.shields.io/badge/PHPStan-level%206-green.svg?style=flat)
[![Latest Stable Version](http://poser.pugx.org/jav/apitopia-bundle/v)](https://packagist.org/packages/jav/apitopia-bundle)
[![Total Downloads](http://poser.pugx.org/jav/apitopia-bundle/downloads)](https://packagist.org/packages/jav/apitopia-bundle)
[![License](http://poser.pugx.org/jav/apitopia-bundle/license)](https://packagist.org/packages/jav/apitopia-bundle)

## ApiTopia

This symfony bundle provides a set of annotations to define a full GraphQL schema, compliant with [Relay specification](https://relay.dev/docs/guides/graphql-server-specification/)

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
- Full schema definition with PHP attributes, and reflection on DTOs
- Named queries, mutations and subscriptions are not suffixed with the class name (you can call them whatever you want)
- Fully configurable subquery and subquery collection
- Paginated collection resolvers are called with 'limit' and 'offset' arguments computed, even with cursor based pagination type
- Deferred subquery and subquery collection
- No ORM/DataSource integration, you have to provide your own data from the resolvers (but you can use anything you want inside them since they are services)
- No support for REST (yet?), only GraphQL

### Read more:
- [Install and configure apitopia](docs/install.md)
- [Define your schema](docs/schema.md)
- [Use subqueries](docs/subqueries.md)
- [Use deferred subqueries](docs/deferred_subqueries.md)
- [Use enums](docs/enums.md)
