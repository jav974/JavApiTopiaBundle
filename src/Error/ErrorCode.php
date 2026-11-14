<?php

namespace Jav\ApiTopiaBundle\Error;

/**
 * Values of the "extensions.code" entry of GraphQL errors (Apollo conventions).
 */
final class ErrorCode
{
    /** The GraphQL document could not be parsed */
    public const GRAPHQL_PARSE_FAILED = 'GRAPHQL_PARSE_FAILED';
    /** The GraphQL document is not valid against the schema (unknown field, wrong argument type, query too deep...) */
    public const GRAPHQL_VALIDATION_FAILED = 'GRAPHQL_VALIDATION_FAILED';
    /** Invalid input: validation constraints, arguments... */
    public const BAD_USER_INPUT = 'BAD_USER_INPUT';
    /** Authentication is required */
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    /** The authenticated user may not do this */
    public const FORBIDDEN = 'FORBIDDEN';
    /** The requested resource does not exist */
    public const NOT_FOUND = 'NOT_FOUND';
    /** Unexpected server error: details are hidden outside debug mode, and logged */
    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';
}
