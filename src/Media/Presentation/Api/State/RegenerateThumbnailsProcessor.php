<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Media\Application\Message\GenerateThumbnailsMessage;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\Service\ImageSecurityPolicy;
use App\Media\Domain\Service\ThumbnailGenerator;
use App\Media\Domain\Service\ThumbnailPolicy;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Infrastructure\Validator\ValidCropArea;
use App\Media\Presentation\Api\Resource\ThumbnailRegenerationRequest;
use App\Media\Presentation\Api\Transformer\ImageResourceTransformer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegenerateThumbnailsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageSecurityPolicy $securityPolicy,
        private readonly ThumbnailGenerator $thumbnailGenerator,
        private readonly ThumbnailPolicy $thumbnailPolicy,
        private readonly ImageResourceTransformer $transformer,
        private readonly MessageBusInterface $messageBus,
        private readonly ValidatorInterface $validator,
        private readonly bool $asyncThumbnails = true,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ThumbnailRegenerationRequest) {
            throw new BadRequestHttpException('Invalid request data.');
        }

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
            throw new AccessDeniedHttpException('You do not have permission to manage this image.');
        }

        // Validate crop area if provided
        if ($data->cropJson) {
            $cropData = is_string($data->cropJson) ? json_decode($data->cropJson, true) : $data->cropJson;

            if (!is_array($cropData)) {
                throw new BadRequestHttpException('Invalid crop data format.');
            }

            // Get image dimensions for validation
            $imagePath = $image->originalPath()->toString();
            if (file_exists($imagePath)) {
                [$width, $height] = getimagesize($imagePath);

                $constraint = new ValidCropArea(
                    maxWidth: $width,
                    maxHeight: $height
                );

                $violations = $this->validator->validate($cropData, $constraint);
                if (count($violations) > 0) {
                    throw new BadRequestHttpException((string) $violations);
                }
            }
        }

        // Determine which sizes to regenerate
        $sizesToRegenerate = [];
        if ($data->sizes && is_array($data->sizes)) {
            foreach ($data->sizes as $size) {
                try {
                    $sizesToRegenerate[] = SizeLabel::fromString($size);
                } catch (\InvalidArgumentException) {
                    throw new BadRequestHttpException("Invalid size label: {$size}");
                }
            }
        } else {
            // Regenerate all sizes if not specified
            $sizesToRegenerate = [
                SizeLabel::SMALL,
                SizeLabel::MEDIUM,
                SizeLabel::LARGE,
                SizeLabel::EXTRA_LARGE,
            ];
        }

        // Prepare crop areas if provided
        $crops = [];
        if ($data->cropJson) {
            $cropData = is_string($data->cropJson) ? json_decode($data->cropJson, true) : $data->cropJson;
            foreach ($sizesToRegenerate as $sizeLabel) {
                $crops[$sizeLabel->value] = CropArea::fromDimensions(
                    (int) ($cropData['x'] ?? 0),
                    (int) ($cropData['y'] ?? 0),
                    (int) ($cropData['width'] ?? 100),
                    (int) ($cropData['height'] ?? 100)
                );
            }
        }

        if ($this->asyncThumbnails) {
            // Dispatch async regeneration
            $this->messageBus->dispatch(new GenerateThumbnailsMessage(
                $imageId,
                $crops,
                $sizesToRegenerate
            ));

            // Return current state (thumbnails will be regenerated in background)
            return $this->transformer->transform($image);
        }
        // Regenerate thumbnails synchronously
        foreach ($sizesToRegenerate as $sizeLabel) {
            $dimensions = $this->thumbnailPolicy->dimensionsFor($sizeLabel);
            $cropArea = $crops[$sizeLabel->value] ?? null;

            $generated = $this->thumbnailGenerator->generate(
                $image,
                $sizeLabel,
                $cropArea ?? CropArea::fromDimensions(0, 0, $dimensions['width'], $dimensions['height'])
            );

            $thumbnail = $image->newThumbnail(
                $sizeLabel,
                $generated->path,
                $generated->width,
                $generated->height,
                $generated->cropArea
            );

            $image->addOrUpdateThumbnail($thumbnail);
        }

        $this->imageRepository->save($image);

        // Reload image from database to get fresh thumbnails
        $reloadedImage = $this->imageRepository->findById($imageId);

        return $this->transformer->transform($reloadedImage ?? $image);
    }
}
