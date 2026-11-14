<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

/**
 * Deserialized mutation inputs are validated with the Symfony Validator.
 */
class ValidationTest extends AbstractApiTestCase
{
    // With an input class, the input type is the class (here ValidatedInput)
    private const MUTATION = 'mutation ($input: ValidatedInput!) { %s(input: $input) { clientMutationId } }';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function mutate(string $mutation, array $input): array
    {
        return $this->graphQL('/test/graphql/test2', sprintf(self::MUTATION, $mutation), ['input' => $input]);
    }

    public function testValidInput(): void
    {
        $response = $this->mutate('createValidated', ['email' => 'jane@example.com', 'name' => 'Jane', 'age' => 12]);

        $this->assertSame(['data' => ['createValidated' => ['clientMutationId' => null]]], $response);
    }

    public function testViolationsAreReturnedWithTheirPaths(): void
    {
        $response = $this->mutate('createValidated', [
            'email' => 'not an email',
            'name' => 'J',
            'age' => 12,
            'child' => ['weight' => -1],
        ]);

        $this->assertSame(['createValidated' => null], $response['data']);
        $error = $response['errors'][0];
        $this->assertSame('Validation failed', $error['message']);
        $this->assertSame(['createValidated'], $error['path']);
        $this->assertSame('BAD_USER_INPUT', $error['extensions']['code']);
        $this->assertSame(
            ['input.email', 'input.name', 'input.child.weight'],
            array_column($error['extensions']['violations'], 'path')
        );
        $this->assertSame('This value is not a valid email address.', $error['extensions']['violations'][0]['message']);
        $this->assertNotEmpty($error['extensions']['violations'][0]['code']);
    }

    public function testValidationGroups(): void
    {
        $response = $this->mutate('createValidatedStrict', ['email' => 'not an email', 'name' => 'J', 'age' => 12]);

        // Only the "strict" group: email (Default group) is not checked
        $this->assertSame(['input.name', 'input.age'], array_column($response['errors'][0]['extensions']['violations'], 'path'));
    }

    public function testValidationCanBeDisabled(): void
    {
        $response = $this->mutate('createNotValidated', ['email' => 'not an email', 'name' => 'J', 'age' => 12]);

        $this->assertArrayNotHasKey('errors', $response);
    }
}
