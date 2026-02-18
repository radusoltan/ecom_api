<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Pricing\Domain\ValueObject\PriceChange;

/**
 * DTO for price history responses.
 *
 * Used for API responses and serialization.
 */
final readonly class PriceHistoryDTO
{
    /**
     * @param array<string, mixed>|null $oldPrice
     * @param array<string, mixed>|null $newPrice
     * @param array<string, mixed>|null $priceChangeDifference
     */
    public function __construct(
        public string $productId,
        public ?array $oldPrice,
        public ?array $newPrice,
        public ?string $reason,
        public string $source,
        public string $sourceLabel,
        public string $timestamp,
        public ?array $priceChangeDifference,
        public ?float $priceChangePercentage,
        public bool $isPriceIncrease,
        public bool $isPriceDecrease,
    ) {
    }

    public static function fromPriceChange(PriceChange $priceChange): self
    {
        return new self(
            productId: $priceChange->productId()->toString(),
            oldPrice: $priceChange->oldPrice()?->toArray(),
            newPrice: $priceChange->newPrice()?->toArray(),
            reason: $priceChange->reason(),
            source: $priceChange->source()->value,
            sourceLabel: $priceChange->source()->label(),
            timestamp: $priceChange->timestamp()->format(\DateTimeImmutable::ATOM),
            priceChangeDifference: $priceChange->priceChangeDifference()?->toArray(),
            priceChangePercentage: $priceChange->priceChangePercentage(),
            isPriceIncrease: $priceChange->isPriceIncrease(),
            isPriceDecrease: $priceChange->isPriceDecrease()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'old_price' => $this->oldPrice,
            'new_price' => $this->newPrice,
            'reason' => $this->reason,
            'source' => $this->source,
            'source_label' => $this->sourceLabel,
            'timestamp' => $this->timestamp,
            'price_change_difference' => $this->priceChangeDifference,
            'price_change_percentage' => $this->priceChangePercentage,
            'is_price_increase' => $this->isPriceIncrease,
            'is_price_decrease' => $this->isPriceDecrease,
        ];
    }
}
