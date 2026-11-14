<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SchemaErrorsTest extends KernelTestCase
{
    private function schemaBuilder(): SchemaBuilder
    {
        return self::getContainer()->get('jav_apitopia.graphql.schema_builder');
    }

    public function testMutationInputMustBeAClass(): void
    {
        $schemaBuilder = $this->schemaBuilder();
        $schemaBuilder->setConfig(['bad' => ['resource_directories' => [dirname(__DIR__, 2) . '/Unit/Fixtures/BadMutation']]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The input of mutation "badMutation" must be a class');
        $schemaBuilder->getSchema('bad');
    }

    public function testUnknownSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema "nope" not found.');
        $this->schemaBuilder()->getSchema('nope');
    }

    public function testSchemasAreBuiltOnce(): void
    {
        $schemaBuilder = $this->schemaBuilder();

        $this->assertSame($schemaBuilder->getSchema('test1'), $schemaBuilder->getSchema('test1'));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function duplicates(): iterable
    {
        yield ['DuplicateQuery', 'Duplicate operation name "duplicated" in Query schema "dup"'];
        yield ['DuplicateMutation', 'Duplicate operation name "duplicated" in Mutation schema "dup"'];
        yield ['DuplicateSubscription', 'Duplicate operation name "duplicated" in Subscription schema "dup"'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('duplicates')]
    public function testDuplicateOperationNames(string $directory, string $message): void
    {
        $schemaBuilder = $this->schemaBuilder();
        $schemaBuilder->setConfig(['dup' => ['resource_directories' => [dirname(__DIR__, 2) . '/Unit/Fixtures/' . $directory]]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        $schemaBuilder->getSchema('dup');
    }
}
