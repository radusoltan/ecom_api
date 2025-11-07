<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

/**
 * SearchFacet Value Object.
 *
 * Represents a faceted filter with buckets (e.g., categories, price ranges, brands).
 */
final readonly class SearchFacet
{
    /**
     * @param array<FacetBucket> $buckets
     */
    public function __construct(
        public string $field,
        public string $label,
        public array $buckets,
    ) {
    }

    public function hasBuckets(): bool
    {
        return !empty($this->buckets);
    }

    public function getBucketCount(): int
    {
        return \count($this->buckets);
    }

    /**
     * Convert to array for API serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'buckets' => array_map(fn (FacetBucket $bucket) => $bucket->toArray(), $this->buckets),
        ];
    }
}
