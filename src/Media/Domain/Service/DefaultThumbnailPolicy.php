<?php

declare(strict_types=1);

namespace App\Media\Domain\Service;

use App\Media\Domain\ValueObject\SizeLabel;

final class DefaultThumbnailPolicy implements ThumbnailPolicy
{
    /**
     * @var array<string, array{width:int,height:int}>
     */
    private const SIZE_MAP = [
        'sm' => ['width' => 200, 'height' => 200],
        'md' => ['width' => 600, 'height' => 400],
        'lg' => ['width' => 1200, 'height' => 800],
        'xl' => ['width' => 1600, 'height' => 900],
    ];

    public function allowedSizes(): array
    {
        return [
            SizeLabel::SMALL,
            SizeLabel::MEDIUM,
            SizeLabel::LARGE,
            SizeLabel::EXTRA_LARGE,
        ];
    }

    public function dimensionsFor(SizeLabel $sizeLabel): array
    {
        $key = $sizeLabel->value;

        if (!isset(self::SIZE_MAP[$key])) {
            throw new \InvalidArgumentException(sprintf('No dimensions defined for size "%s".', $key));
        }

        return self::SIZE_MAP[$key];
    }

    public function assertValid(SizeLabel $sizeLabel, int $width, int $height): void
    {
        $expected = $this->dimensionsFor($sizeLabel);

        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Thumbnail dimensions must be positive integers.');
        }

        if ($width < (int) floor($expected['width'] * 0.5) || $height < (int) floor($expected['height'] * 0.5)) {
            throw new \InvalidArgumentException(sprintf('Thumbnail dimensions for %s are too small. Expected at least %dx%d pixels.', $sizeLabel->value, $expected['width'], $expected['height']));
        }
    }
}
