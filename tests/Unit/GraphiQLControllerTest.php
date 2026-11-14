<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\Controller\GraphiQLController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GraphiQLControllerTest extends TestCase
{
    public function testUnknownSchema(): void
    {
        $this->expectException(NotFoundHttpException::class);
        (new GraphiQLController(['main' => '/graphql']))->index('other');
    }

    public function testTwigIsRequired(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TwigBundle');
        (new GraphiQLController(['main' => '/graphql']))->index('main');
    }
}
