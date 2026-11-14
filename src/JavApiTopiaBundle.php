<?php

namespace Jav\ApiTopiaBundle;

use Jav\ApiTopiaBundle\DependencyInjection\ApiTopiaExtension;
use Jav\ApiTopiaBundle\DependencyInjection\Compiler\NodeResolverPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class JavApiTopiaBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new NodeResolverPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ApiTopiaExtension
    {
        return new ApiTopiaExtension();
    }
}
