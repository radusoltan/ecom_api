<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

/**
 * FacetBucket Value Object
 *
 * Represents a single bucket in a faceted filter (e.g., "Electronics" category with count 42).
 */
final readonly class FacetBucket
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public bool $selected = false,
    ) {
        if ($this->count < 0) {
            throw new \InvalidArgumentException('Bucket count cannot be negative');
        }
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * Convert to array for API serialization
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'count' => $this->count,
            'selected' => $this->selected,
        ];
    }
}
