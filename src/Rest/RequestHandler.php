<?php

namespace Jav\ApiTopiaBundle\Rest;

use Exception;
use Jav\ApiTopiaBundle\Api\Attributes\Rest\Attribute;
use Jav\ApiTopiaBundle\Api\ResolverInterface;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use JsonException;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestHandler
{
    private ServiceLocator $locator;
    private Serializer $serializer;

    public function __construct(ServiceLocator $locator, Serializer $serializer)
    {
        $this->locator = $locator;
        $this->serializer = $serializer;
    }

    public function handleResponse(Request $request): ?Response
    {
        $config = $request->attributes->get('_apitopia');
        /** @var Attribute $attribute */
        $attribute = unserialize($config['attr']);
        /** @var ResolverInterface $resolver */
        $resolver = $this->locator->get($config['resolver']['class']);

        try {
            $reflectionMethod = new \ReflectionMethod($config['resolver']['class'], $config['resolver']['method']);
            $params = $this->parseParams($reflectionMethod->getParameters(), $request);
            $data = $reflectionMethod->invokeArgs($resolver, $params);

            $this->checkOutput($attribute, $data);

            return $this->serialize($attribute, $data);
        } catch (\ReflectionException|Exception $e) {
        }

        return null;
    }

    /**
     * Fetch parameters from request object
     * Deserialize the parameters to objects if resolver method signature requires so
     *
     * @param ReflectionParameter[] $reflectionParams
     * @return array<mixed>
     * @throws JsonException
     */
    private function parseParams(array $reflectionParams, Request $request): array
    {
        $params = [];

        foreach ($reflectionParams as $param) {
            $type = $param->getType();
            $isScalar = false;
            $typeName = null;

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $isScalar = in_array($typeName, ['int', 'string', 'bool', 'array']);
            }

            $data = $request->get($param->getName());

            // Transform array to object of type '$typeName' since the method only accepts $typeName type as parameter
            if (!$isScalar) {
                $data = $this->serializer->deserialize(json_encode($data, JSON_THROW_ON_ERROR), $typeName);
            }

            $params[$param->getName()] = $data;
        }

        return $params;
    }

    private function serialize(Attribute $attribute, mixed $data): Response
    {
        return match ($attribute->outputType) {
            Attribute::OUTPUT_TYPE_JSON => new Response(
                $this->serializer->serialize($data),
                200,
                ['Content-Type' => 'application/json']
            ),
            Attribute::OUTPUT_TYPE_XML => new Response(
                $this->serializer->serialize($data, 'xml'),
                200,
                ['Content-Type' => 'application/xml']
            ),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function checkOutput(Attribute $attribute, mixed $data): void
    {
        $outputIsArray = is_array($attribute->output);
        $outputIsArrayObject = $outputIsArray && !empty($attribute->output);
        $outputIsArray = $outputIsArray || $attribute->output === 'array';
        $dataIsArray = is_array($data);

        if ($outputIsArrayObject && !$dataIsArray) {
            $dataClass = get_class($data);
            throw new Exception("Invalid output type: Expecting array of {$attribute->output[0]}, got {$dataClass}");
        }

        if ($outputIsArray && !$dataIsArray) {
            $dataClass = get_class($data);
            throw new Exception("Invalid output type: Expecting array, got {$dataClass}");
        }

        if (!$outputIsArray && $dataIsArray) {
            throw new Exception("Invalid output type: Expecting {$attribute->output}, got array");
        }

        if (!$outputIsArray) {
            $dataClass = get_class($data);

            if ($dataClass !== $attribute->output) {
                throw new Exception("Invalid output type: Expecting {$attribute->output}, got {$dataClass}");
            }
        }

        if ($outputIsArrayObject) {
            foreach ($data as $datum) {
                $datumClass = get_class($datum);

                if ($datumClass !== $attribute->output[0]) {
                    throw new Exception("Invalid output type: Should contain array of {$attribute->output[0]}, got {$datumClass} as well");
                }
            }
        }
    }
}
