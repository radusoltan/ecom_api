<?php

declare(strict_types=1);

namespace App\Media\Domain\Service;

use App\Media\Domain\Model\Image;
use App\Media\Domain\Service\DTO\GeneratedThumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\SizeLabel;

interface ThumbnailGenerator
{
    public function generate(Image $image, SizeLabel $sizeLabel, CropArea $cropArea): GeneratedThumbnail;
}
