<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Thumbnail;

use App\Media\Domain\Model\Image;
use App\Media\Domain\Service\DTO\GeneratedThumbnail;
use App\Media\Domain\Service\ThumbnailGenerator;
use App\Media\Domain\Service\ThumbnailPolicy;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\SizeLabel;
use Symfony\Component\Filesystem\Filesystem;

final class LocalThumbnailGenerator implements ThumbnailGenerator
{
    private const SUPPORTED_MIME_HANDLERS = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    private Filesystem $filesystem;

    public function __construct(
        private readonly string $basePath,
        private readonly string $publicPrefix,
        private readonly string $originalBasePath,
        private readonly string $originalPublicPrefix,
        private readonly ThumbnailPolicy $thumbnailPolicy,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function generate(Image $image, SizeLabel $sizeLabel, CropArea $cropArea): GeneratedThumbnail
    {
        $dimensions = $this->thumbnailPolicy->dimensionsFor($sizeLabel);
        $this->thumbnailPolicy->assertValid($sizeLabel, $dimensions['width'], $dimensions['height']);

        $relativeDirectory = sprintf('%s/%s', $image->tenantId()->toString(), $image->id()->toString());
        $targetDirectory = sprintf('%s/%s', rtrim($this->basePath, '/'), $relativeDirectory);
        $this->filesystem->mkdir($targetDirectory);

        $extension = pathinfo($image->originalPath()->toString(), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = sprintf('%s_%s.%s', $image->id()->toString(), $sizeLabel->value, $extension);
        $targetPath = sprintf('%s/%s', $targetDirectory, $filename);

        $sourceAbsolute = $this->resolveAbsoluteOriginal($image);
        $mime = $this->detectMimeType($sourceAbsolute);

        $source = $this->createImageResource($sourceAbsolute, $mime);
        [$sourceWidth, $sourceHeight] = $this->resourceDimensions($source);

        $crop = $this->normalizeCropArea($cropArea, $sourceWidth, $sourceHeight);
        $cropped = $this->cropImage($source, $crop);
        $resized = $this->resizeImage($cropped, $dimensions['width'], $dimensions['height'], $mime);

        $this->saveImage($resized, $targetPath, $mime);

        imagedestroy($source);
        imagedestroy($cropped);
        imagedestroy($resized);

        $publicPath = sprintf('%s/%s/%s', rtrim($this->publicPrefix, '/'), $relativeDirectory, $filename);

        return new GeneratedThumbnail(
            FilePath::fromString($publicPath),
            $sizeLabel,
            $dimensions['width'],
            $dimensions['height'],
            CropArea::fromDimensions($crop['x'], $crop['y'], $crop['width'], $crop['height'])
        );
    }

    private function resolveAbsoluteOriginal(Image $image): string
    {
        $publicPath = $image->originalPath()->toString();
        $prefix = rtrim($this->originalPublicPrefix, '/');

        if (str_starts_with($publicPath, $prefix)) {
            $relative = ltrim(substr($publicPath, strlen($prefix)), '/');

            return sprintf('%s/%s', rtrim($this->originalBasePath, '/'), $relative);
        }

        return sprintf('%s/%s', rtrim($this->originalBasePath, '/'), ltrim($publicPath, '/'));
    }

    private function detectMimeType(string $path): string
    {
        $info = @getimagesize($path);
        if (false === $info || !isset($info['mime'])) {
            throw new \RuntimeException(sprintf('Unable to detect mime type for image "%s".', $path));
        }

        return $info['mime'];
    }

    private function createImageResource(string $path, string $mime)
    {
        $handler = self::SUPPORTED_MIME_HANDLERS[$mime] ?? null;

        if (null === $handler) {
            throw new \RuntimeException(sprintf('Unsupported image mime type "%s".', $mime));
        }

        return match ($handler) {
            'jpeg' => $this->ensureResource(imagecreatefromjpeg($path)),
            'png' => $this->ensureResource(imagecreatefrompng($path)),
            'webp' => $this->ensureResource(imagecreatefromwebp($path)),
            'gif' => $this->ensureResource(imagecreatefromgif($path)),
            'avif' => $this->ensureResource(function_exists('imagecreatefromavif') ? imagecreatefromavif($path) : null),
            default => throw new \RuntimeException(sprintf('No handler available for mime "%s".', $mime)),
        };
    }

    private function ensureResource(mixed $resource)
    {
        if (false === $resource || null === $resource) {
            throw new \RuntimeException('Failed to create image resource.');
        }

        return $resource;
    }

    private function resourceDimensions($resource): array
    {
        return [imagesx($resource), imagesy($resource)];
    }

    /**
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function normalizeCropArea(CropArea $cropArea, int $sourceWidth, int $sourceHeight): array
    {
        $x = max(0, min($cropArea->x(), $sourceWidth - 1));
        $y = max(0, min($cropArea->y(), $sourceHeight - 1));

        $width = min($cropArea->width(), $sourceWidth - $x);
        $height = min($cropArea->height(), $sourceHeight - $y);

        if ($width <= 0 || $height <= 0) {
            $width = $sourceWidth;
            $height = $sourceHeight;
            $x = 0;
            $y = 0;
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function cropImage($resource, array $crop)
    {
        $cropped = imagecrop($resource, $crop);
        if (false === $cropped) {
            throw new \RuntimeException('Failed to crop image.');
        }

        return $cropped;
    }

    private function resizeImage($resource, int $targetWidth, int $targetHeight, string $mime)
    {
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if (false === $resized) {
            throw new \RuntimeException('Failed to create target image.');
        }

        $this->configureTransparency($resized, $mime);

        $result = imagecopyresampled(
            $resized,
            $resource,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($resource),
            imagesy($resource)
        );

        if (false === $result) {
            imagedestroy($resized);

            throw new \RuntimeException('Failed to resize image.');
        }

        return $resized;
    }

    private function configureTransparency($resource, string $mime): void
    {
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
            imagealphablending($resource, false);
            imagesavealpha($resource, true);
            $transparent = imagecolorallocatealpha($resource, 0, 0, 0, 127);
            imagefill($resource, 0, 0, $transparent);
        }
    }

    private function saveImage($resource, string $path, string $mime): void
    {
        $this->filesystem->mkdir(dirname($path));

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($resource, $path, 90);

                break;
            case 'image/png':
                imagepng($resource, $path, 6);

                break;
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    throw new \RuntimeException('WEBP support not available in current GD build.');
                }
                imagewebp($resource, $path, 80);

                break;
            case 'image/gif':
                imagegif($resource, $path);

                break;
            case 'image/avif':
                if (!function_exists('imageavif')) {
                    throw new \RuntimeException('AVIF support not available in current GD build.');
                }
                imageavif($resource, $path, 50);

                break;
            default:
                throw new \RuntimeException(sprintf('Unable to save image of type "%s".', $mime));
        }
    }
}
