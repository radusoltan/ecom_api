<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

final readonly class CropArea
{
    private function __construct(
        private int $x,
        private int $y,
        private int $width,
        private int $height
    ) {}

    public static function fromDimensions(int $x, int $y, int $width, int $height): self
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Crop area width and height must be greater than zero.');
        }

        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Crop area coordinates cannot be negative.');
        }

        return new self($x, $y, $width, $height);
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    /**
     * @return array{x:int,y:int,width:int,height:int}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
