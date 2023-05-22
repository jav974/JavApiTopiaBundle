<?php


namespace Jav\ApiTopiaBundle\Serializer;


use ArrayObject;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

class Serializer
{
    private BaseSerializer $serializer;

    public function __construct()
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new UploadedFileDenormalizer(), new ObjectNormalizer(null, null, null, new ReflectionExtractor())];
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
     * @param object|array<mixed>|null $data
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>|null
     * @throws ExceptionInterface
     */
    public function normalize(object|array|null $data): array|string|int|float|bool|ArrayObject|null
    {
        return $this->serializer->normalize($data);
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
    public function denormalizeUploadedFiles(array &$data): void
    {
        foreach ($data as &$datum) {
            if ($datum instanceof UploadedFileInterface) {
                $datum = $this->serializer->denormalize($datum, UploadedFile::class);
            } elseif (is_array($datum)) {
                $this->denormalizeUploadedFiles($datum);
            }
        }
    }
}
