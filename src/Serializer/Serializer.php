<?php

namespace Jav\ApiTopiaBundle\Serializer;

use ArrayObject;
use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

class Serializer
{
    private BaseSerializer $serializer;

    public function __construct(private readonly ResourceLoader $resourceLoader)
    {
        $classMetadataFactory = $this->resourceLoader->getClassMetatadaFactory();
        $encoders = [new JsonEncoder(), new XmlEncoder()];
        $extractors = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizers = [
            new DateTimeNormalizer(),
            new UploadedFileDenormalizer(),
            new ObjectNormalizer(classMetadataFactory: $classMetadataFactory, propertyTypeExtractor: $extractors),
            new ArrayDenormalizer(),
//            new ObjectNormalizer(classMetadataFactory: $classMetadataFactory, propertyTypeExtractor: new ReflectionExtractor()),
        ];
        $this->serializer = new BaseSerializer($normalizers, $encoders);
    }

    /**
     * @param object|array<mixed>|null $data
     */
    public function serialize(object|array|null $data, string $format = 'json'): string
    {
        return $this->serializer->serialize($data, $format);
    }

    public function deserialize(string $data, string $type, string $format = 'json'): mixed
    {
        return $this->serializer->deserialize($data, $type, $format);
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>|null
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, array $context = []): array|string|int|float|bool|ArrayObject|null
    {
        return $this->serializer->normalize($data, context: $context);
    }

    /**
     * @param array<mixed> $data
     * @return object|array<mixed>
     * @throws ExceptionInterface
     */
    public function denormalize(array $data, string $type): mixed
    {
        return $this->serializer->denormalize($data, $type);
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
                $datum = $this->serializer->denormalize($datum, UploadedFile::class);
            }
        }
    }
}
