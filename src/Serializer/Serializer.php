<?php

namespace Jav\ApiTopiaBundle\Serializer;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

class Serializer extends BaseSerializer
{
    private PropertyInfoExtractor $extractor;

    public function __construct(private readonly ResourceLoader $resourceLoader)
    {
        $classMetadataFactory = $this->resourceLoader->getClassMetatadaFactory();
        $encoders = [new JsonEncoder()];
        $descriptionExtractors = [new AttributeTypeExtractor($classMetadataFactory), new PhpDocExtractor()];
        $typeExtractors = array_merge($descriptionExtractors, [new ReflectionExtractor()]);
        $this->extractor = new PropertyInfoExtractor([new SerializerExtractor($classMetadataFactory), new ReflectionExtractor()], $typeExtractors, $descriptionExtractors);
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

    public function getPropertyInfoExtractor(): PropertyInfoExtractor
    {
        return $this->extractor;
    }
}
