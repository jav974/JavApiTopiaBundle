<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use GraphQL\Upload\UploadType;
use GraphQLRelay\Connection\Connection;
use InvalidArgumentException;

final class TypeRegistry
{
    /** @var array<string, Type&NullableType> */
    private array $types = [];

    public function __construct()
    {
        $this->types = Type::getStandardTypes();
        $this->types['PageInfo'] = Connection::pageInfoType();
        $this->types['input.Upload'] = new UploadType();
        $this->types['Upload'] =& $this->types['input.Upload'];
    }

    public function register(string $name, Type&NullableType $definition): void
    {
        $this->types[$name] = $definition;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $name): Type&NullableType
    {
        return $this->types[$name] ??
            throw new InvalidArgumentException(sprintf('Type "%s" not found in registry.', $name));
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }
}
