<?php

namespace Jav\ApiTopiaBundle\Pagination;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class VirtualArrayPaginator implements IteratorAggregate, PaginatorInterface
{
    /**
     * @param array<mixed> $results
     */
    public function __construct(
        private readonly array $results,
        private readonly int $offset,
        private readonly int $totalItems
    ) {
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getCurrentPageResults(): array
    {
        return $this->results;
    }

    public function getCurrentPageOffset(): int
    {
        return $this->offset >= 0 ? $this->offset : $this->totalItems + $this->offset;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getCurrentPageResults());
    }
}
