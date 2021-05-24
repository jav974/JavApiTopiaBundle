<?php

namespace Jav\ApiTopiaBundle\Api\Attributes\Rest;

#[\Attribute]
class Put extends Attribute
{
    public function __construct(string $path, array|string $output, string $name, ?string $description = null, ?string $outputType = null)
    {
        parent::__construct($path, self::METHOD_PUT, $output, $name, $description, $outputType);
    }
}
