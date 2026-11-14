<?php

namespace Jav\ApiTopiaBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class GraphiQLController
{
    /**
     * @param array<string> $endpoints
     */
    public function __construct(
        private readonly array $endpoints,
        private readonly ?Environment $twig = null,
    ) {
    }

    public function index(string $schema): Response
    {
        if (!isset($this->endpoints[$schema])) {
            throw new NotFoundHttpException(sprintf('Schema "%s" not found', $schema));
        }

        if ($this->twig === null) {
            throw new \LogicException('GraphiQL requires the TwigBundle to be enabled.');
        }

        return new Response($this->twig->render('@JavApiTopia/GraphiQL/index.html.twig', [
            'graphql_endpoint' => $this->endpoints[$schema],
        ]));
    }
}
