<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\MessageHandler;

use App\Media\Application\Message\DeleteImageAssetsMessage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteImageAssetsHandler
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly string $originalBasePath,
        private readonly string $originalPublicPrefix,
        private readonly string $thumbnailBasePath,
        private readonly string $thumbnailPublicPrefix,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function __invoke(DeleteImageAssetsMessage $message): void
    {
        $this->removeFile($message->imagePath, $this->originalPublicPrefix, $this->originalBasePath);

        foreach ($message->thumbnailPaths as $thumbnailPath) {
            $this->removeFile($thumbnailPath, $this->thumbnailPublicPrefix, $this->thumbnailBasePath);
        }
    }

    private function removeFile(string $publicPath, string $publicPrefix, string $basePath): void
    {
        $relative = str_starts_with($publicPath, $publicPrefix)
            ? ltrim(substr($publicPath, strlen($publicPrefix)), '/')
            : ltrim($publicPath, '/');

        $absolute = sprintf('%s/%s', rtrim($basePath, '/'), $relative);

        if ($this->filesystem->exists($absolute)) {
            $this->filesystem->remove($absolute);
        }
    }
}
