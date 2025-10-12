<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final readonly class ProductImage
{
    private function __construct(
        private string $url,
        private int $position,
        private bool $isPrimary
    ) {}

    public static function create(string $url, int $position = 0, bool $isPrimary = false): self
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Image URL cannot be empty');
        }

        return new self($url, $position, $isPrimary);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    /**
     * @return array{url: string, position: int, isPrimary: bool}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'position' => $this->position,
            'isPrimary' => $this->isPrimary
        ];
    }

    /**
     * @param array{url: string, position?: int, isPrimary?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['url'],
            $data['position'] ?? 0,
            $data['isPrimary'] ?? false
        );
    }
}
