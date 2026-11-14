<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures\BadMutation;

/**
 * An enum cannot be used as mutation input.
 */
enum BadMutationInput: string
{
    case A = 'a';
}
