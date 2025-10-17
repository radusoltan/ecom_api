<?php

declare(strict_types=1);

namespace App\Media\Application\Message;

use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;

final readonly class GenerateThumbnailsMessage
{
    /**
     * @param array<string, CropArea|null> $crops Crop areas per size label
     * @param SizeLabel[]|null $sizes Specific sizes to generate, null for all
     */
    public function __construct(
        public ImageId $imageId,
        public array $crops = [],
        public ?array $sizes = null
    ) {}
}