<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Media\Presentation\Api\Transformer\ImageResourceTransformer;
use App\Shared\Domain\ValueObject\TenantId;

final class ImageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageResourceTransformer $transformer
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $filters = $context['filters'] ?? [];
        $tenantFilter = $filters['tenantId'] ?? null;

        if (null === $tenantFilter) {
            return [];
        }

        $images = $this->imageRepository->findByTenant(TenantId::fromString((string) $tenantFilter));

        return array_map(
            fn ($image): ImageResource => $this->transformer->transform($image),
            $images
        );
    }
}
