<?php

namespace Jav\ApiTopiaBundle\Cache;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ResourcesWarmer implements CacheWarmerInterface
{
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly ?SchemaBuilder $schemaBuilder = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->config as $schemaName => $config) {
            $this->resourceLoader->loadResources($schemaName, $config['resource_directories'], true);
            // Builds the schema index, so that no request has to build the whole schema
            $this->schemaBuilder?->getSchema($schemaName);
        }

        return [];
    }
}
