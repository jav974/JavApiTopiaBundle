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

If you plan to build a RESTful API, import apitopia routing configuration
by adding these lines:

```yaml
// config/routes.yaml

apitopia:
  resource: .
  type: apitopia
```

### Step 4: Using the Bundle

In apitopia, everything is centered arround resolvers. If you want to build
a new endpoint, whether it is REST or GraphQL, you have to provide
an implementation of ResolverInterface.

Classes that inherit ResolverInterface will be automatically tagged as apitopia.resolver
and be processed for Attributes parsing.

Annotations provided are:
- Get
- Post
- Put
- Delete

and for GraphQL:
- Query
- Mutation

Considering this dummy object as an entity:
```php

// src/App/Entity/Dummy.php

class Dummy
{
    public int $id;
    public string $name;
    public string $description;
}

```


Let's take a simple example:

```php

// src/App/Resolver/DummyResolver.php

use Jav\ApiTopiaBundle\Api\Attributes\Rest\Delete;
use Jav\ApiTopiaBundle\Api\Attributes\Rest\Get;
use Jav\ApiTopiaBundle\Api\Attributes\Rest\Post;
use Jav\ApiTopiaBundle\Api\ResolverInterface;
use App\Entity\Dummy;

class DummyResolver implements ResolverInterface
{
    #[Get(path: '/dummy/{id}', output: Dummy::class, name: 'api_dummy_get')]
    public function getItem(int $id)
    {
        $object = new Dummy();
        
        $object->id = $id;
        $object->name = 'Dude';
        $object->description = 'First ever dummy';
        
        return $object;
    }
    
    #[Get(path: '/dummy/{id}', output: [Dummy::class], name: 'api_dummy_collection')]
    public function getCollection()
    {
        $object = new Dummy();
        $object->id = 1;
        $object->name = 'Dude';
        $object->description = 'First ever dummy';
        
        $object2 = new Dummy();
        $object2->id = 2;
        $object2->name = 'Dudette';
        $object2->description = 'Second only dummy';
        
        return [$object, $object2];
    }
    
    #[Post(path: '/dummy/create', output: Dummy::class, name: 'api_dummy_create')]
    public function postItem(Dummy $dummy)
    {
        // Process the dummy...
        // This dummy comes from the Request object, and should be posted with name 'dummy'
        // in order for the wiring process to work. Then deserialization takes place and transforms it to a Dummy object
        
        return $dummy;
    }
    
    #[Post(path: '/dummy/{id}/update', output: [], name: 'api_dummy_update', outputType: 'application/xml')]
    public function putItem(int $id, array $dummy)
    {
        // Process the dummy...
        // The id argument refers to the one in the url.
        // Every argument is tested against the Request object to be passed in this method
        // This time we want the dummy as an array instead of a Dummy object
        
        return ['message' => 'Item successfully updated !']; // Will be transformed to xml
    }
    
    #[Delete(path: '/dummy/{id}/delete', output: [], name: 'api_dummy_delete')]
    public function deleteItem(int $id)
    {
        // ...
        return ['ok'];
    }
}

```

This example will expose 4 routes in the symfony router.
ApiTopia examines the method arguments to provide them with the appropriate format of input data.
It can take care of de-serialization of any plain old PHP objects using symfony serializer component.

You can however provide your own serializer by decorating the Jav\ApiTopiaBundle\Serializer\Serializer::class

The `output` attribute of any annotation can contain either a string 'array' or any FQN, like `Dummy::class`
For collection output, you can use an array with the desired FQN like so: `[Dummy::class]`
You can also return a simple array with any kind of content with the 'array' or [] notation

By default, the endpoints produce json data. This can be tuned by setting the `outputClass` on the attribute.
Currently, only 'application/json' and 'application/xml' are implemented.