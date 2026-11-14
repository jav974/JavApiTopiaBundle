<?php

namespace Jav\ApiTopiaBundle\Pagination;

/**
 * @extends \Traversable<int, mixed>
 */
interface PaginatorInterface extends \Traversable, \Countable
{
    public function getTotalItems(): int;

    /**
     * @return array<mixed>
     */
    public function getCurrentPageResults(): array;

    public function getCurrentPageOffset(): int;
}
