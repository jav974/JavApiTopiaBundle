<?php

namespace Jav\ApiTopiaBundle\Cache;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;

if (Kernel::VERSION_ID < 7000) {
    class ResourcesWarmer implements CacheWarmerInterface
    {
        /** @var array<string, mixed> */
        private array $config;

        public function __construct(private readonly ResourceLoader $resourceLoader)
        {
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

        public function warmUp(string $cacheDir): array
        {
            foreach ($this->config as $schemaName => $config) {
                $this->resourceLoader->loadResources($schemaName, $config['resource_directories'], true);
            }

            return [];
        }
    }
} else {
    class ResourcesWarmer implements CacheWarmerInterface
    {
        /** @var array<string, mixed> */
        private array $config;

        public function __construct(private readonly ResourceLoader $resourceLoader)
        {
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

        public function warmUp(string $cacheDir, ?string $buildDir = null): array
        {
            foreach ($this->config as $schemaName => $config) {
                $this->resourceLoader->loadResources($schemaName, $config['resource_directories'], true);
            }

            return [];
        }
    }
}
