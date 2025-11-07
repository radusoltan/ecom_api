<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Serializer;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class UploadedFileDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): UploadedFile
    {
        if (!$data instanceof UploadedFile) {
            throw new \InvalidArgumentException('Expected uploaded file instance.');
        }

        return $data;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UploadedFile;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            UploadedFile::class => true,
        ];
    }
}
