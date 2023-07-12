<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL;

class DeferredResults implements \ArrayAccess
{
    /** @var array<int, object[]>> */
    private array $results = [];

    /**
     * @return object[]
     */
    public function getUnresolvedParents(): array
    {
        $unresolved = [];

        foreach ($this->results as $result) {
            if (empty($result[1])) {
                $unresolved[] = $result[0];
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

    public function offsetExists(mixed $offset): bool
    {
        return $this->getOffsetForParent($offset) !== false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        foreach ($this->results as $result) {
            if ($result[0] === $offset) {
                return $result[1];
            }
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offsetForParent = $this->getOffsetForParent($offset);

        if ($offsetForParent === false) {
            $this->results[] = [$offset, $value];
        } else {
            $this->results[$offsetForParent][1] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        foreach ($this->results as $key => $result) {
            if ($result[0] === $offset) {
                unset($this->results[$key]);
            }
        }
    }

    private function getOffsetForParent(object $parent): int|false
    {
        foreach ($this->results as $key => $result) {
            if ($result[0] === $parent) {
                return $key;
            }
        }

        return false;
    }
}
