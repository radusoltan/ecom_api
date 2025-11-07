<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\MessageHandler;

use App\Media\Application\Message\GenerateThumbnailsMessage;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\Service\ThumbnailGenerator;
use App\Media\Domain\Service\ThumbnailPolicy;
use App\Media\Domain\ValueObject\SizeLabel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateThumbnailsHandler
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ThumbnailGenerator $thumbnailGenerator,
        private readonly ThumbnailPolicy $thumbnailPolicy,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        try {
            $image = $this->imageRepository->findById($message->imageId);
            if (!$image) {
                $this->logger->warning('Image not found for thumbnail generation', [
                    'imageId' => $message->imageId->toString(),
                ]);

                return;
            }

            // Determine which sizes to generate
            $sizesToGenerate = $message->sizes ?? [
                SizeLabel::SMALL,
                SizeLabel::MEDIUM,
                SizeLabel::LARGE,
                SizeLabel::EXTRA_LARGE,
            ];

            $this->logger->info('Starting async thumbnail generation', [
                'imageId' => $message->imageId->toString(),
                'sizes' => array_map(fn ($size) => $size->value, $sizesToGenerate),
            ]);

            foreach ($sizesToGenerate as $sizeLabel) {
                try {
                    // Get crop area for this size if provided
                    $cropArea = $message->crops[$sizeLabel->value] ?? null;

                    // Get dimensions from policy
                    $dimensions = $this->thumbnailPolicy->getDimensionsForSize($sizeLabel);

                    // Generate thumbnail
                    $thumbnail = $this->thumbnailGenerator->generate(
                        $image,
                        $sizeLabel,
                        $dimensions['width'],
                        $dimensions['height'],
                        $cropArea
                    );

                    // Add or update thumbnail in image
                    $image->addOrUpdateThumbnail($thumbnail);

                    $this->logger->debug('Generated thumbnail', [
                        'imageId' => $message->imageId->toString(),
                        'size' => $sizeLabel->value,
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to generate thumbnail', [
                        'imageId' => $message->imageId->toString(),
                        'size' => $sizeLabel->value,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Save image with updated thumbnails
            $this->imageRepository->save($image);

            $this->logger->info('Completed async thumbnail generation', [
                'imageId' => $message->imageId->toString(),
                'thumbnailsGenerated' => count($sizesToGenerate),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Thumbnail generation failed', [
                'imageId' => $message->imageId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
