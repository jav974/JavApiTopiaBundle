<?php

namespace Jav\ApiTopiaBundle\Serializer;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class UploadedFileDenormalizer implements DenormalizerInterface
{
    /**
     * @param UploadedFileInterface|UploadedFile $data
     * @param array<mixed> $context
     * @throws InvalidArgumentException
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): ?UploadedFile
    {
        if ($data instanceof UploadedFile) {
            return $data;
        }

        $srcFileName = tempnam(sys_get_temp_dir(), 'UploadedFileDenormalizer');

        if (!$srcFileName) {
            throw new InvalidArgumentException('Could not create temporary file.');
        }

        file_put_contents($srcFileName, $data->getStream()->getContents());

        return new UploadedFile(
            $srcFileName,
            $data->getClientFilename()
        );
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === UploadedFile::class && ($data instanceof UploadedFileInterface || $data instanceof UploadedFile);
    }
}
