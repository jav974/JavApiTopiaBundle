<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Enums;

use GraphQL\Type\Definition\Description;

#[Description('Sort direction')]
enum Sort: string
{
    #[Description('Ascending')]
    case ASC = 'asc';
    #[Description('Descending')]
    case DESC = 'desc';
}
