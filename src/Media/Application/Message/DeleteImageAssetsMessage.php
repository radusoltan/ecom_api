<?php

declare(strict_types=1);

namespace App\Media\Application\Message;

final class DeleteImageAssetsMessage
{
    /**
     * @param array<int, string> $thumbnailPaths
     */
    public function __construct(
        public readonly string $imagePath,
        public readonly array $thumbnailPaths,
    ) {
    }
}
