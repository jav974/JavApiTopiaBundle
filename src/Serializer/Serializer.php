<?php

namespace Jav\ApiTopiaBundle\Serializer;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

class Serializer extends BaseSerializer
{
    private PropertyInfoExtractor $extractor;

    private PropertyInfoExtractorInterface $cachedExtractor;

    /**
     * @param CacheItemPoolInterface|null $cache Caches property information (types, descriptions); not used in debug mode
     */
    public function __construct(private readonly ResourceLoader $resourceLoader, ?CacheItemPoolInterface $cache = null)
    {
        $classMetadataFactory = $this->resourceLoader->getClassMetatadaFactory();
        $encoders = [new JsonEncoder()];
        $descriptionExtractors = [new AttributeTypeExtractor($classMetadataFactory), new PhpDocExtractor()];
        $typeExtractors = array_merge($descriptionExtractors, [new ReflectionExtractor()]);
        $this->extractor = new PropertyInfoExtractor([new SerializerExtractor($classMetadataFactory), new ReflectionExtractor()], $typeExtractors, $descriptionExtractors);
        $this->cachedExtractor = $cache !== null ? new PropertyInfoCacheExtractor($this->extractor, $cache) : $this->extractor;
        $normalizers = [
            new DateTimeNormalizer(),
            new UploadedFileDenormalizer(),
            new ObjectNormalizer(classMetadataFactory: $classMetadataFactory, propertyTypeExtractor: $this->extractor),
            new ArrayDenormalizer(),
        ];

        parent::__construct($normalizers, $encoders);
    }

    /**
     * @param array<mixed> $data
     * @throws ExceptionInterface
     */
    public function denormalizeInput(array &$data): void
    {
        foreach ($data as &$datum) {
            if (is_array($datum)) {
                $this->denormalizeInput($datum);
            } elseif ($datum instanceof UploadedFileInterface) {
                $datum = $this->denormalize($datum, UploadedFile::class);
            }
        }
    }

    /**
     * @internal The property information extractor used to build schemas, cached outside debug mode
     */
    public function getCachedPropertyInfoExtractor(): PropertyInfoExtractorInterface
    {
        return $this->cachedExtractor;
    }

    public function getPropertyInfoExtractor(): PropertyInfoExtractor
    {
        return $this->extractor;
    }
}
