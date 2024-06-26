Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Prerequisites
-------------

```json
// composer.json
{
  "require": {
    "ivome/graphql-relay-php": "@dev"
  }
}
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

