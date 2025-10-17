<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Media\Presentation\Api\Transformer\ImageResourceTransformer;

final class ImageItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageResourceTransformer $transformer
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ImageResource
    {
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            return null;
        }

        $image = $this->imageRepository->findById(ImageId::fromString((string) $id));

        if ($image === null) {
            return null;
        }

        return $this->transformer->transform($image);
    }
}
