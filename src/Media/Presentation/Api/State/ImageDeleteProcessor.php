<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Media\Application\Message\DeleteImageAssetsMessage;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\Service\ImageSecurityPolicy;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImageDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageSecurityPolicy $securityPolicy,
        private readonly MessageBusInterface $messageBus
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!isset($uriVariables['id'])) {
            throw new NotFoundHttpException('Image not found.');
        }

        $imageId = ImageId::fromString($uriVariables['id']);
        $image = $this->imageRepository->findById($imageId);

        if (!$image) {
            throw new NotFoundHttpException('Image not found.');
        }

        // Check security permissions
        if (!$this->securityPolicy->canManage($image->tenantId(), $image->owner())) {
            throw new AccessDeniedHttpException('You do not have permission to delete this image.');
        }

        // Delete from repository (soft delete if implemented, otherwise hard delete)
        $this->imageRepository->remove($image);

        // Dispatch message to clean up physical files asynchronously
        $this->messageBus->dispatch(new DeleteImageAssetsMessage(
            $imageId->toString(),
            $image->originalPath()->toString(),
            array_map(fn($thumbnail) => $thumbnail->path()->toString(), $image->thumbnails())
        ));
    }
}