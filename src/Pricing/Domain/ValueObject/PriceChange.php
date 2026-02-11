<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Represents a price change event in the price history.
 *
 * This value object encapsulates all information about a price change,
 * including the product, old/new prices, reason, source, and timestamp.
 *
 * Business Rules:
 * - immutable record (no updates after creation)
 * - timestamp must be in the past or present
 * - reason is optional but recommended
 * - source must be valid enum value
 */
final readonly class PriceChange
{
    private function __construct(
        private ProductId $productId,
        private ?Money $oldPrice,
        private ?Money $newPrice,
        private ?string $reason,
        private PriceChangeSource $source,
        private \DateTimeImmutable $timestamp
    ) {
        $this->validate();
    }

    public static function create(
        ProductId $productId,
        ?Money $oldPrice,
        ?Money $newPrice,
        ?string $reason,
        PriceChangeSource $source,
        ?\DateTimeImmutable $timestamp = null
    ): self {
        return new self(
            $productId,
            $oldPrice,
            $newPrice,
            $reason,
            $source,
            $timestamp ?? new \DateTimeImmutable()
        );
    }

    public static function fromPriceListRule(
        ProductId $productId,
        ?Money $oldPrice,
        Money $newPrice,
        ?string $reason = null
    ): self {
        return self::create(
            $productId,
            $oldPrice,
            $newPrice,
            $reason ?? 'Price list rule applied',
            PriceChangeSource::MANUAL
        );
    }

    public static function fromPromotion(
        ProductId $productId,
        Money $oldPrice,
        Money $newPrice,
        string $promotionName
    ): self {
        return self::create(
            $productId,
            $oldPrice,
            $newPrice,
            sprintf('Promotion applied: %s', $promotionName),
            PriceChangeSource::PROMOTION
        );
    }

    public static function fromFlashSale(
        ProductId $productId,
        Money $oldPrice,
        Money $newPrice,
        string $flashSaleName
    ): self {
        return self::create(
            $productId,
            $oldPrice,
            $newPrice,
            sprintf('Flash sale: %s', $flashSaleName),
            PriceChangeSource::FLASH_SALE
        );
    }

    public static function fromImport(
        ProductId $productId,
        ?Money $oldPrice,
        Money $newPrice,
        string $importSource
    ): self {
        return self::create(
            $productId,
            $oldPrice,
            $newPrice,
            sprintf('Imported from: %s', $importSource),
            PriceChangeSource::IMPORT
        );
    }

    private function validate(): void
    {
        // At least one price (old or new) must be present
        if ($this->oldPrice === null && $this->newPrice === null) {
            throw new \InvalidArgumentException('At least one price (old or new) must be specified');
        }

        // Timestamp cannot be in the future
        $now = new \DateTimeImmutable();
        if ($this->timestamp > $now) {
            throw new \InvalidArgumentException('Price change timestamp cannot be in the future');
        }

        // Reason cannot be empty string (null is OK)
        if ($this->reason !== null && trim($this->reason) === '') {
            throw new \InvalidArgumentException('Price change reason cannot be an empty string');
        }

        // If both prices exist, they must have the same currency
        if ($this->oldPrice !== null && $this->newPrice !== null) {
            if ($this->oldPrice->currency()->getCurrencyCode() !== $this->newPrice->currency()->getCurrencyCode()) {
                throw new \InvalidArgumentException('Old and new prices must have the same currency');
            }
        }
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function oldPrice(): ?Money
    {
        return $this->oldPrice;
    }

    public function newPrice(): ?Money
    {
        return $this->newPrice;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function source(): PriceChangeSource
    {
        return $this->source;
    }

    public function timestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function isPriceIncrease(): bool
    {
        if ($this->oldPrice === null || $this->newPrice === null) {
            return false;
        }

        return $this->newPrice->isGreaterThan($this->oldPrice);
    }

    public function isPriceDecrease(): bool
    {
        if ($this->oldPrice === null || $this->newPrice === null) {
            return false;
        }

        return $this->newPrice->isLessThan($this->oldPrice);
    }

    public function priceChangeDifference(): ?Money
    {
        if ($this->oldPrice === null || $this->newPrice === null) {
            return null;
        }

        return $this->newPrice->subtract($this->oldPrice);
    }

    public function priceChangePercentage(): ?float
    {
        if ($this->oldPrice === null || $this->newPrice === null) {
            return null;
        }

        if ($this->oldPrice->isZero()) {
            return null; // Avoid division by zero
        }

        $difference = $this->newPrice->subtract($this->oldPrice);
        $percentage = $difference->amount() / $this->oldPrice->amount() * 100;

        return round($percentage, 2);
    }

    public function equals(self $other): bool
    {
        return $this->productId->equals($other->productId)
            && $this->timestamp->getTimestamp() === $other->timestamp->getTimestamp()
            && $this->source->equals($other->source)
            && $this->reason === $other->reason
            && (($this->oldPrice === null && $other->oldPrice === null) || ($this->oldPrice?->equals($other->oldPrice) ?? false))
            && (($this->newPrice === null && $other->newPrice === null) || ($this->newPrice?->equals($other->newPrice) ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId->toString(),
            'old_price' => $this->oldPrice?->toArray(),
            'new_price' => $this->newPrice?->toArray(),
            'reason' => $this->reason,
            'source' => $this->source->value,
            'timestamp' => $this->timestamp->format(\DateTimeImmutable::ATOM),
            'price_change_difference' => $this->priceChangeDifference()?->toArray(),
            'price_change_percentage' => $this->priceChangePercentage(),
            'is_price_increase' => $this->isPriceIncrease(),
            'is_price_decrease' => $this->isPriceDecrease(),
        ];
    }
}
