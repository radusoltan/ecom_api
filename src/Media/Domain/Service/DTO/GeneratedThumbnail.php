<?php

declare(strict_types=1);

namespace App\Media\Domain\Service\DTO;

use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\SizeLabel;

final readonly class GeneratedThumbnail
{
    public function __construct(
        public FilePath $path,
        public SizeLabel $sizeLabel,
        public int $width,
        public int $height,
        public CropArea $cropArea,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Generated thumbnail dimensions must be greater than zero.');
        }
    }
}
