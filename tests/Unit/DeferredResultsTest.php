<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use PHPUnit\Framework\TestCase;

class DeferredResultsTest extends TestCase
{
    public function testRegisteredParentsAreUnresolvedUntilSet(): void
    {
        $results = new DeferredResults();
        $a = new \stdClass();
        $b = new \stdClass();

        $results->register($a);
        $results->register($b);
        $results->register($a);

        $this->assertSame([$a, $b], $results->getUnresolvedParents());

        $results->set($a, null);
        $this->assertSame([$b], $results->getUnresolvedParents(), 'A null result is still a result');

        $results[$b] = ['x'];
        $this->assertSame([], $results->getUnresolvedParents());
        $this->assertSame(['x'], $results[$b]);
        $this->assertNull($results[$a]);
    }

    public function testMarkResolvedKeepsExistingResults(): void
    {
        $results = new DeferredResults();
        $a = new \stdClass();
        $b = new \stdClass();
        $results->register($a);
        $results->register($b);
        $results->set($a, $a);

        $results->markResolved([$a, $b]);

        $this->assertSame([], $results->getUnresolvedParents());
        $this->assertSame($a, $results[$a]);
        $this->assertNull($results[$b]);
    }

    public function testUnset(): void
    {
        $results = new DeferredResults();
        $a = new \stdClass();
        $results->set($a, $a);

        $this->assertTrue(isset($results[$a]));
        unset($results[$a]);
        $this->assertFalse(isset($results[$a]));
        $this->assertFalse(isset($results['not an object']));
    }

    public function testUnknownParentAndInvalidKey(): void
    {
        $results = new DeferredResults();

        $this->assertNull($results[new \stdClass()]);

        $this->expectException(\InvalidArgumentException::class);
        $results['not an object'] = null;
    }
}
