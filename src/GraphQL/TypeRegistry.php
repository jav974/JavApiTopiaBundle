<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use GraphQL\Upload\UploadType;
use Jav\ApiTopiaBundle\GraphQL\Relay\RelayTypes;
use InvalidArgumentException;

final class TypeRegistry
{
    /** @var array<string, Type&NullableType> */
    private array $types = [];

    /** @var string[] What is being built: "query:<name>", "mutation:<name>", "subscription:<name>" or "type:<name>" */
    private array $ownerStack = [];

    /** @var array<string, array<string, string>> Schema name => GraphQL type name => owner that creates it */
    private array $owners = [];

    public function __construct()
    {
        $this->types = Type::getStandardTypes();
        $this->types['PageInfo'] = RelayTypes::pageInfo();
        $this->types['input.Upload'] = new UploadType();
        $this->types['Upload'] =& $this->types['input.Upload'];
    }

    public function register(string $name, Type&NullableType $definition): void
    {
        $this->types[$name] = $definition;

        $separator = strpos($name, '.');

        if ($this->ownerStack !== [] && $separator !== false && isset($definition->name)) {
            $schemaName = substr($name, 0, $separator);
            $this->owners[$schemaName][$definition->name] ??= $this->ownerStack[count($this->ownerStack) - 1];
        }
    }

    /**
     * Runs $build, recording $owner as the creator of the types it registers.
     * Lazily built schemas use it to know what to build to obtain a given type.
     *
     * @template T
     * @param callable(): T $build
     * @return T
     */
    public function withOwner(string $owner, callable $build): mixed
    {
        $this->ownerStack[] = $owner;

        try {
            return $build();
        } finally {
            array_pop($this->ownerStack);
        }
    }

    /**
     * @return array<string, string> GraphQL type name => owner
     */
    public function getOwners(string $schemaName): array
    {
        return $this->owners[$schemaName] ?? [];
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
