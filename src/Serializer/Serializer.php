<?php


namespace Jav\ApiTopiaBundle\Serializer;


use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

class Serializer
{
    private SerializerInterface $serializer;

    public function __construct(?SerializerInterface $serializer = null)
    {
        if ($serializer) {
            $this->serializer = $serializer;
        } else {
            $encoders = [new XmlEncoder(), new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];
            $this->serializer = new BaseSerializer($normalizers, $encoders);
        }
    }

    public function serialize(object|array $data, string $format = 'json'): string
    {
        return $this->serializer->serialize($data, $format);
    }

    public function deserialize(string $data, string $type, string $format = 'json')
    {
        return $this->serializer->deserialize($data, $type, $format);
    }
}
