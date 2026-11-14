<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL;

use ArrayAccess;
use SplObjectStorage;

/**
 * Holds the results of a deferred (batched) resolver, indexed by parent object.
 *
 * @implements ArrayAccess<mixed, object|array<mixed>|null>
 */
class DeferredResults implements ArrayAccess
{
    /** @var SplObjectStorage<object, array{0: object|array<mixed>|null, 1: bool}> parent => [result, resolved] */
    private SplObjectStorage $results;

    public function __construct()
    {
        $this->results = new SplObjectStorage();
    }

    /**
     * Returns the parents for which the resolver has not been executed yet.
     *
     * @return object[]
     */
    public function getUnresolvedParents(): array
    {
        $unresolved = [];

        foreach ($this->results as $parent) {
            if (!$this->results[$parent][1]) {
                $unresolved[] = $parent;
            }
        }

        return $unresolved;
    }

    /**
     * @param object|object[]|null $result
     */
    public function set(object $parent, object|array|null $result): void
    {
        $this->offsetSet($parent, $result);
    }

    /**
     * @internal Registers a parent waiting to be resolved
     */
    public function register(object $parent): void
    {
        if (!isset($this->results[$parent])) {
            $this->results[$parent] = [null, false];
        }
    }

    /**
     * @internal Marks the given parents as resolved, even if the resolver did not provide any result for them
     * @param object[] $parents
     */
    public function markResolved(array $parents): void
    {
        foreach ($parents as $parent) {
            $this->results[$parent] = [isset($this->results[$parent]) ? $this->results[$parent][0] : null, true];
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_object($offset) && isset($this->results[$offset]);
    }

    /**
     * @return object|object[]|null
     */
    public function offsetGet(mixed $offset): object|array|null
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }

        return $this->results[$offset][0];
    }

    /**
     * @param object|object[]|null $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_object($offset)) {
            throw new \InvalidArgumentException('DeferredResults keys must be the parent objects.');
        }

        $this->results[$offset] = [$value, true];
    }

    /**
     * @param object $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->results[$offset]);
        }
    }
}
