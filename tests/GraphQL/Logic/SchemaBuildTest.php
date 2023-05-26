<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SchemaBuildTest extends KernelTestCase
{
    private SchemaBuilder $schemaBuilder;

    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->schemaBuilder = $container->get(SchemaBuilder::class);
    }

    public function testBuild(): void
    {
        $this->schemaBuilder->build();

        $this->assertFileExists(__DIR__ . '/../Output/schema.test1.graphql');
        $this->assertFileExists(__DIR__ . '/../Output/schema.test2.graphql');
    }
}
