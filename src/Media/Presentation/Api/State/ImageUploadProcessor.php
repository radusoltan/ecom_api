<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Media\Application\Message\GenerateThumbnailsMessage;
use App\Media\Domain\Model\Image;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\Service\ImageSecurityPolicy;
use App\Media\Domain\Service\ImageStorage;
use App\Media\Domain\Service\ThumbnailGenerator;
use App\Media\Domain\Service\ThumbnailPolicy;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Media\Presentation\Api\Transformer\ImageResourceTransformer;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImageUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageStorage $imageStorage,
        private readonly ThumbnailGenerator $thumbnailGenerator,
        private readonly ThumbnailPolicy $thumbnailPolicy,
        private readonly ImageResourceTransformer $transformer,
        private readonly ImageSecurityPolicy $securityPolicy,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly bool $asyncThumbnails = true,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImageResource
    {
        if (!$data instanceof ImageResource) {
            throw new BadRequestHttpException('Invalid media payload.');
        }

        if (null === $data->file) {
            throw new BadRequestHttpException('Uploaded file is required.');
        }

        if (null === $data->tenantId || null === $data->ownerId || null === $data->ownerType) {
            throw new BadRequestHttpException('Tenant, owner type, and owner id are required.');
        }

        $tenantId = TenantId::fromString($data->tenantId);
        $ownerType = OwnerType::fromString($data->ownerType);
        $ownerReference = OwnerReference::from($tenantId, $ownerType, $data->ownerId);

        if (!$this->securityPolicy->canManage($tenantId, $ownerReference)) {
            throw new AccessDeniedHttpException('You do not have permission to manage media for this tenant.');
        }

        try {
            $detectedMime = $data->file->getMimeType();
        } catch (\InvalidArgumentException) {
            $detectedMime = null;
        }

        $mime = $detectedMime ?? $data->file->getClientMimeType() ?? 'image/jpeg';
        $mimeType = MimeType::fromString($mime);
        $fileSize = (int) ($data->file->getSize() ?? 0);

        $imageId = ImageId::generate();
        $storedPath = $this->imageStorage->storeOriginal($data->file, $tenantId, $imageId);

        $image = Image::upload(
            $tenantId,
            $ownerReference,
            $mimeType,
            $fileSize,
            $storedPath,
            $data->title,
            $data->altText,
            new \DateTimeImmutable(),
            $imageId
        );

        if (OwnerType::PRODUCT === $ownerType) {
            $this->attachImageToProduct($tenantId, $ownerReference->ownerId(), $image);
        }

        if (OwnerType::CATEGORY === $ownerType) {
            $this->attachImageToCategory($tenantId, $ownerReference->ownerId(), $image);
        }

        // Save the image first
        $this->imageRepository->save($image);

        // Generate thumbnails - either async or sync based on configuration
        if ($this->asyncThumbnails) {
            // Dispatch async thumbnail generation
            $this->messageBus->dispatch(new GenerateThumbnailsMessage($imageId));
        } else {
            // Generate thumbnails synchronously (for immediate availability)
            foreach ($this->thumbnailPolicy->allowedSizes() as $sizeLabel) {
                $dimensions = $this->thumbnailPolicy->dimensionsFor($sizeLabel);
                $cropArea = CropArea::fromDimensions(0, 0, $dimensions['width'], $dimensions['height']);

                $generated = $this->thumbnailGenerator->generate($image, $sizeLabel, $cropArea);

                $thumbnail = $image->newThumbnail(
                    $sizeLabel,
                    $generated->path,
                    $generated->width,
                    $generated->height,
                    $generated->cropArea
                );

                $image->addOrUpdateThumbnail($thumbnail);
            }

            // Save again with thumbnails
            $this->imageRepository->save($image);
        }

        return $this->transformer->transform($image);
    }

    private function attachImageToProduct(TenantId $tenantId, string $ownerId, Image $image): void
    {
        try {
            $productId = ProductId::fromString($ownerId);
        } catch (\InvalidArgumentException) {
            return;
        }

        $product = $this->productRepository->findById($productId);

        if (null === $product || !$product->tenantId()->equals($tenantId)) {
            return;
        }

        $position = count($product->images());
        $productImage = ProductImage::create(
            $image->originalPath()->toString(),
            $position,
            0 === $position
        );

        $product->addImage($productImage);
        $this->productRepository->save($product);
    }

    private function attachImageToCategory(TenantId $tenantId, string $ownerId, Image $image): void
    {
        try {
            $categoryId = CategoryId::fromString($ownerId);
        } catch (\InvalidArgumentException) {
            return;
        }

        $category = $this->categoryRepository->findById($categoryId);

        if (null === $category || !$category->tenantId()->equals($tenantId)) {
            return;
        }

        $category->assignCoverImage($image->originalPath()->toString());
        $this->categoryRepository->save($category);
    }
}
