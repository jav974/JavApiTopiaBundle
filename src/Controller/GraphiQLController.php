<?php

namespace Jav\ApiTopiaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class GraphiQLController extends AbstractController
{
    /**
     * @param array<string> $endpoints
     */
    public function __construct(private readonly array $endpoints)
    {
    }

    public function index(string $schema): Response
    {
        if (!isset($this->endpoints[$schema])) {
            throw $this->createNotFoundException("Schema $schema not found");
        }

        return $this->render('@JavApiTopia\GraphiQL\index.html.twig', ['graphql_endpoint' => $this->endpoints[$schema]]);
    }
}
