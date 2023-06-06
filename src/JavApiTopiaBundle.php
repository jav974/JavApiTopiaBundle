<?php

namespace Jav\ApiTopiaBundle;

use Jav\ApiTopiaBundle\DependencyInjection\ApiTopiaExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class JavApiTopiaBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ApiTopiaExtension
    {
        return new ApiTopiaExtension();
    }
}
