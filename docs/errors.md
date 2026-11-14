# Errors and validation

## Error codes

Every GraphQL error has a code in `extensions.code`, following the Apollo conventions:

| Code | When |
|---|---|
| `GRAPHQL_PARSE_FAILED` | The query cannot be parsed |
| `GRAPHQL_VALIDATION_FAILED` | The query is not valid against the schema (unknown field, too deep...) |
| `BAD_USER_INPUT` | Invalid input: validation constraints, variable values, `UserError`... |
| `UNAUTHENTICATED` | Authentication is required |
| `FORBIDDEN` | Access denied |
| `NOT_FOUND` | The resource does not exist |
| `INTERNAL_SERVER_ERROR` | Any other exception |

```json
{
  "errors": [{
    "message": "Order 42 not found",
    "path": ["order"],
    "extensions": {"code": "NOT_FOUND"}
  }],
  "data": {"order": null}
}
```

**Internal errors** keep their details private. The message is `Internal server error`, and the exception is logged with level `error` on the `apitopia` channel. Details are added to `extensions` only in debug mode.

## Returning errors from resolvers

Throw a `ClientException`. Its message is always sent to the client:

```php
use Jav\ApiTopiaBundle\Error\ClientException;
use Jav\ApiTopiaBundle\Error\ErrorCode;

throw ClientException::notFound('Order 42 not found');
throw ClientException::forbidden();
throw ClientException::unauthenticated();
throw new ClientException('Quota exceeded', 'QUOTA_EXCEEDED', ['retryAfter' => 60]); // custom code and extensions
```

These exceptions are mapped automatically:

| Exception | Code | Message |
|---|---|---|
| `HttpExceptionInterface` 401 / 403 / 404 / other 4xx | `UNAUTHENTICATED` / `FORBIDDEN` / `NOT_FOUND` / `BAD_USER_INPUT` | exception message |
| `HttpExceptionInterface` 5xx | `INTERNAL_SERVER_ERROR` | hidden |
| Symfony Security `AuthenticationException` | `UNAUTHENTICATED` | `Authentication required` |
| Symfony Security `AccessDeniedException` | `FORBIDDEN` | `Access denied` |
| Symfony Validator `ValidationFailedException` | `BAD_USER_INPUT` | `Validation failed`, with `violations` |
| graphql-php `UserError` | `BAD_USER_INPUT` | exception message |

### Mapping your own exceptions

To expose domain exceptions without depending on the bundle in your domain code:

```yaml
api_topia:
  error_mapping:
    App\Domain\OrderNotFound: NOT_FOUND
    App\Domain\PaymentFailed: { code: PAYMENT_FAILED, expose_message: false }
```

Subclasses are matched too. The mapping takes precedence over the built-in rules above. With `expose_message: false`, the message is `Internal server error`, but the code is still sent to the client and the error is not logged.

## Validation

When `symfony/validator` is installed, deserialized mutation inputs (a `Mutation` with an `input` class) are validated with the constraints of that class:

```php
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserInput
{
    #[Assert\Email]
    public string $email;

    #[Assert\Length(min: 3)]
    public string $name;

    #[Assert\Valid] // validates nested objects
    public ?AddressInput $address = null;
}
```

Violations are returned as one error, and the resolver is not called:

```json
{
  "errors": [{
    "message": "Validation failed",
    "path": ["createUser"],
    "extensions": {
      "code": "BAD_USER_INPUT",
      "violations": [
        {"path": "input.email", "message": "This value is not a valid email address.", "code": "bd79c0ab-ddba-46cc-a703-a7a4b08de310"},
        {"path": "input.address.zipCode", "message": "This value should not be blank.", "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3"}
      ]
    }
  }],
  "data": {"createUser": null}
}
```

Options of the `Mutation` attribute:

```php
new Mutation(name: 'createUser', resolver: CreateUserResolver::class, input: CreateUserInput::class, validationGroups: ['Default', 'creation']);
new Mutation(name: 'importUser', resolver: ImportUserResolver::class, input: CreateUserInput::class, validate: false);
```

To validate something else in a resolver, throw `new ValidationException($violations)` (or Symfony's `ValidationFailedException`).

## HTTP status codes

- A response with GraphQL errors has the status `api_topia.error_http_status_code` (500 by default, 200 recommended).
- An invalid request, such as a malformed JSON body, gets a 400.
