<?php


namespace Jav\ApiTopiaBundle\Api\Attributes\Rest;

use Jav\ApiTopiaBundle\Api\Attributes\Attribute as BaseAttribute;

#[\Attribute]
abstract class Attribute extends BaseAttribute
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    const OUTPUT_TYPE_JSON = 'application/json';
    const OUTPUT_TYPE_XML = 'application/xml';

    public string $method;

    public string $path;

    public string $outputType = self::OUTPUT_TYPE_JSON;

    /**
     * Query constructor.
     * @param string $path
     * @param string $method
     * @param string|string[] $output
     * @param string $name
     * @param string|null $description
     * @param string|null $outputType
     */
    public function __construct(string $path, string $method, array|string $output, string $name, ?string $description = null, ?string $outputType = null)
    {
        parent::__construct($output, $name, $description);

        $this->path = $path;
        $this->method = $method;
        $this->output = $output;
        $this->name = $name;
        $this->description = $description;

        if ($outputType) {
            $this->outputType = $outputType;
        }
    }
}
