<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\Service\ImageSecurityPolicy;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Media\Presentation\Api\Transformer\ImageResourceTransformer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ImageResource, ImageResource>
 */
final readonly class UpdateImageMetadataProcessor implements ProcessorInterface
{
    public function __construct(
        private ImageRepositoryInterface $imageRepository,
        private ImageSecurityPolicy $securityPolicy,
        private ImageResourceTransformer $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImageResource
    {
        if (!isset($uriVariables['id'])) {
            throw new NotFoundHttpException('Image not found.');
        }

        $imageId = ImageId::fromString($uriVariables['id']);
        $image = $this->imageRepository->findById($imageId);

        if (!$image) {
            throw new NotFoundHttpException('Image not found.');
        }

        if (!$this->securityPolicy->canManage($image->tenantId(), $image->owner())) {
            throw new AccessDeniedHttpException('You do not have permission to update this image.');
        }

        assert($data instanceof ImageResource);

        $image->rename($data->title, $data->altText);
        $this->imageRepository->save($image);

        return $this->transformer->transform($image);
    }
}
