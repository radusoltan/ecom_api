<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Serializer;

use App\Media\Presentation\Api\Resource\ImageResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MediaObjectNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'MEDIA_OBJECT_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer,
        #[Autowire('%media.storage.local.public_prefix%')]
        private readonly string $publicPrefix
    ) {}

    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        // Build the content URL based on the image ID and original path
        if ($object->id && !$object->contentUrl) {
            $object->contentUrl = $this->publicPrefix . '/' . $object->tenantId . '/' .
                                  $object->ownerType . '/' . $object->ownerId . '/' .
                                  $object->id . '/original.jpg';
        }

        // Build thumbnail URLs if thumbnails exist
        if ($object->thumbnails && is_array($object->thumbnails)) {
            foreach ($object->thumbnails as &$thumbnail) {
                if (isset($thumbnail['sizeLabel']) && !isset($thumbnail['url'])) {
                    $thumbnail['url'] = $this->publicPrefix . '/' . $object->tenantId . '/' .
                                       $object->ownerType . '/' . $object->ownerId . '/' .
                                       $object->id . '/thumb_' . $thumbnail['sizeLabel'] . '.jpg';
                }
            }
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof ImageResource;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ImageResource::class => true,
        ];
    }
}