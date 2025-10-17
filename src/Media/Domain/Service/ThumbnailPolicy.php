<?php

declare(strict_types=1);

namespace App\Media\Domain\Service;

use App\Media\Domain\ValueObject\SizeLabel;

interface ThumbnailPolicy
{
    /**
     * @return SizeLabel[]
     */
    public function allowedSizes(): array;

    /**
     * @return array{width:int,height:int}
     */
    public function dimensionsFor(SizeLabel $sizeLabel): array;

    public function assertValid(SizeLabel $sizeLabel, int $width, int $height): void;
}
