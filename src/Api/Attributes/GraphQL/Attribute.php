<?php


namespace Jav\ApiTopiaBundle\Api\Attributes\GraphQL;

use Jav\ApiTopiaBundle\Api\Attributes\Attribute as BaseAttribute;

#[\Attribute]
abstract class Attribute extends BaseAttribute
{
    /**
     * Can be set to 'relay', otherwise it is ignored
     * @var string|null
     */
    public ?string $spec = null;

    /**
     * Query constructor.
     * @param string|string[] $output
     * @param string|null $name
     * @param string|null $description
     * @param string|null $spec
     */
    public function __construct(array|string $output, ?string $name = null, ?string $description = null, ?string $spec = null)
    {
        parent::__construct($output, $name, $description);
        $this->spec = $spec;
    }
}
