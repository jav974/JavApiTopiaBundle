<?php

namespace Jav\ApiTopiaBundle\Api\Attributes;

abstract class Attribute
{
    /**
     * The optional name of the endpoint
     * Will be generated if missing
     * @var string|null
     */
    public ?string $name = null;

    /**
     * An optional description that will be added to the generated schema
     * @var string|null
     */
    public ?string $description = null;

    /**
     * The output type for this query
     * For item query, put the desired FQN: Entity::class
     * For collection query, put the desired FQN as array: [Entity::class]
     * @var string|string[]
     */
    public string|array|null $output;

    /**
     * Query constructor.
     * @param string|string[]|null $output
     * @param string|null $name
     * @param string|null $description
     */
    public function __construct(array|string|null $output, ?string $name = null, ?string $description = null)
    {
        $this->output = $output;
        $this->name = $name;
        $this->description = $description;
    }
}
