<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use App\Media\Domain\Service\ImageStorage;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LocalImageStorage implements ImageStorage
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly string $basePath,
        private readonly string $publicPrefix = '/media/originals'
    ) {
        $this->filesystem = new Filesystem();
    }

    public function storeOriginal(UploadedFile $file, TenantId $tenantId, ImageId $imageId): FilePath
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $tenantDir = $tenantId->toString();
        $targetDirectory = sprintf('%s/%s', rtrim($this->basePath, '/'), $tenantDir);
        $this->filesystem->mkdir($targetDirectory);

        $filename = sprintf('%s.%s', $imageId->toString(), $extension);
        $file->move($targetDirectory, $filename);

        $publicPath = sprintf('%s/%s/%s', rtrim($this->publicPrefix, '/'), $tenantDir, $filename);

        return FilePath::fromString($publicPath);
    }

    public function remove(FilePath $path): void
    {
        $absolute = $this->resolveAbsolutePath($path);

        if ($this->filesystem->exists($absolute)) {
            $this->filesystem->remove($absolute);
        }
    }

    private function resolveAbsolutePath(FilePath $path): string
    {
        $publicPath = $path->toString();
        $prefix = rtrim($this->publicPrefix, '/');

        if (!str_starts_with($publicPath, $prefix)) {
            // If already absolute, return as-is
            return $publicPath;
        }

        $relative = ltrim(substr($publicPath, strlen($prefix)), '/');

        return sprintf('%s/%s', rtrim($this->basePath, '/'), $relative);
    }
}
