<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;

/**
 * Variant entity (child entity of ConfigurableProduct)
 * Represents a specific combination of option values
 *
 * Business Rules:
 * - Each variant must have exactly one value per option
 * - SKU must be unique per tenant
 * - Option value map must not be empty
 * - Price and stock can be different from parent product
 * - Images are specific to the variant
 *
 * @property array<string, string> $optionValueMap Map of option code to value code (e.g., ['color' => 'red', 'size' => 'xl'])
 */
final class Variant
{
    /**
     * @param array<string, string> $optionValueMap
     * @param ProductImage[] $images
     */
    private function __construct(
        private VariantId $id,
        private VariantSKU $sku,
        private array $optionValueMap,
        private Money $price,
        private Stock $stock,
        private array $images,
        private bool $isActive,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
        $this->validateOptionValueMap($optionValueMap);
    }

    /**
     * Create a new variant
     *
     * @param array<string, string> $optionValueMap
     */
    public static function create(
        VariantId $id,
        VariantSKU $sku,
        array $optionValueMap,
        Money $price,
        Stock $stock,
        bool $isActive = true
    ): self {
        return new self(
            id: $id,
            sku: $sku,
            optionValueMap: $optionValueMap,
            price: $price,
            stock: $stock,
            images: [],
            isActive: $isActive,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Reconstitute from persistence
     *
     * @param array<string, string> $optionValueMap
     * @param ProductImage[] $images
     */
    public static function reconstituteFromPersistence(
        VariantId $id,
        VariantSKU $sku,
        array $optionValueMap,
        Money $price,
        Stock $stock,
        array $images,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $sku,
            $optionValueMap,
            $price,
            $stock,
            $images,
            $isActive,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * Update variant details
     */
    public function update(
        Money $price,
        Stock $stock,
        bool $isActive
    ): void {
        $this->price = $price;
        $this->stock = $stock;
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Update price
     */
    public function updatePrice(Money $price): void
    {
        $this->price = $price;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Update stock
     */
    public function updateStock(Stock $stock): void
    {
        $this->stock = $stock;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Activate variant
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Deactivate variant
     */
    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Add an image to the variant
     */
    public function addImage(ProductImage $image): void
    {
        $this->images[] = $image;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Remove an image from the variant
     */
    public function removeImage(int $position): void
    {
        $this->images = array_filter(
            $this->images,
            fn(ProductImage $img) => $img->position() !== $position
        );
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if variant has a specific option value
     */
    public function hasOptionValue(OptionCode $optionCode, OptionValueCode $valueCode): bool
    {
        $optionCodeStr = $optionCode->value();
        return isset($this->optionValueMap[$optionCodeStr])
            && $this->optionValueMap[$optionCodeStr] === $valueCode->value();
    }

    /**
     * Get the value code for a specific option
     */
    public function getOptionValue(OptionCode $optionCode): ?string
    {
        return $this->optionValueMap[$optionCode->value()] ?? null;
    }

    /**
     * Check if variant matches a specific combination of option values
     *
     * @param array<string, string> $optionValueMap
     */
    public function matchesCombination(array $optionValueMap): bool
    {
        // Both must have the same number of keys
        if (count($this->optionValueMap) !== count($optionValueMap)) {
            return false;
        }

        // Sort keys for comparison (to handle different order)
        $thisKeys = array_keys($this->optionValueMap);
        $thatKeys = array_keys($optionValueMap);
        sort($thisKeys);
        sort($thatKeys);

        // Both must have the same keys (after sorting)
        if ($thisKeys !== $thatKeys) {
            return false;
        }

        // All values must match
        foreach ($optionValueMap as $optionCode => $valueCode) {
            if (!isset($this->optionValueMap[$optionCode]) ||
                $this->optionValueMap[$optionCode] !== $valueCode) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if variant is available for purchase
     */
    public function isAvailable(int $quantity = 1): bool
    {
        return $this->isActive && $this->stock->isAvailable($quantity);
    }

    // Getters
    public function getId(): VariantId
    {
        return $this->id;
    }

    public function getSku(): VariantSKU
    {
        return $this->sku;
    }

    /**
     * @return array<string, string>
     */
    public function getOptionValueMap(): array
    {
        return $this->optionValueMap;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStock(): Stock
    {
        return $this->stock;
    }

    /**
     * @return ProductImage[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    /**
     * Validate option value map
     *
     * @param array<string, string> $optionValueMap
     */
    private function validateOptionValueMap(array $optionValueMap): void
    {
        if (empty($optionValueMap)) {
            throw new \InvalidArgumentException('Variant must have at least one option value');
        }

        foreach ($optionValueMap as $optionCode => $valueCode) {
            if (!is_string($optionCode) || empty($optionCode)) {
                throw new \InvalidArgumentException('Option code must be a non-empty string');
            }

            if (!is_string($valueCode) || empty($valueCode)) {
                throw new \InvalidArgumentException('Value code must be a non-empty string');
            }
        }
    }
}

/**
 * Value object for Variant ID
 */
final readonly class VariantId
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Variant ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(uniqid('var_', true));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
