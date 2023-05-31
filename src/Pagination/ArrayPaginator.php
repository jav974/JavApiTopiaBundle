<?php

namespace Jav\ApiTopiaBundle\Pagination;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class ArrayPaginator implements IteratorAggregate, PaginatorInterface
{
    /** @var array<mixed> */
    private array $items;

    private int $totalItems;

    /**
     * @param array<mixed> $results
     */
    public function __construct(array $results, int $firstResult, int $maxResults)
    {
        $this->items = array_slice(array_values($results), $firstResult, $maxResults, true);
        $this->totalItems = count($results);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getCurrentPageResults(): array
    {
        return array_values($this->items);
    }

    public function getCurrentPageOffset(): int
    {
        return array_key_first($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getCurrentPageResults());
    }
}
