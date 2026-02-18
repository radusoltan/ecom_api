<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Invoice Line Entity.
 *
 * Business Rules:
 * - Description is required (min 3 chars)
 * - Quantity must be positive (>= 1)
 * - Unit price must be positive (>= 0.01)
 * - Tax rate must be between 0 and 100 (percentage)
 * - Line total = quantity * unit price
 * - Tax amount = line total * (tax rate / 100)
 * - Gross total = line total + tax amount
 * - Product ID and SKU are optional (for custom line items)
 * - Position determines display order in invoice
 */
final readonly class InvoiceLine
{
    private function __construct(
        private InvoiceLineId $id,
        private ?ProductId $productId,
        private ?string $sku,
        private string $description,
        private int $quantity,
        private Money $unitPrice,
        private float $taxRate,
        private Money $taxAmount,
        private Money $lineTotal,
        private int $position,
    ) {
    }

    public static function create(
        InvoiceLineId $id,
        string $description,
        int $quantity,
        Money $unitPrice,
        float $taxRate,
        ?ProductId $productId = null,
        ?string $sku = null,
        int $position = 0,
    ): self {
        // Validate inputs
        if (mb_strlen(trim($description)) < 3) {
            throw new \InvalidArgumentException('Line description must be at least 3 characters long');
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException(sprintf('Line quantity must be at least 1, got: %d', $quantity));
        }

        // Unit price must be positive (at least 1 cent)
        if ($unitPrice->isNegative() || $unitPrice->isZero()) {
            throw new \InvalidArgumentException('Line unit price must be positive (at least 0.01)');
        }

        if ($taxRate < 0.0 || $taxRate > 100.0) {
            throw new \InvalidArgumentException(sprintf('Tax rate must be between 0 and 100, got: %.2f', $taxRate));
        }

        if ($position < 0) {
            throw new \InvalidArgumentException(sprintf('Position must be non-negative, got: %d', $position));
        }

        // Calculate line total (quantity * unit price)
        $lineTotal = self::calculateLineTotal($quantity, $unitPrice);

        // Calculate tax amount (line total * tax rate / 100)
        $taxAmount = self::calculateTaxAmount($lineTotal, $taxRate);

        return new self(
            id: $id,
            productId: $productId,
            sku: $sku ? trim($sku) : null,
            description: trim($description),
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            taxAmount: $taxAmount,
            lineTotal: $lineTotal,
            position: $position
        );
    }

    public static function reconstituteFromPersistence(
        InvoiceLineId $id,
        ?ProductId $productId,
        ?string $sku,
        string $description,
        int $quantity,
        Money $unitPrice,
        float $taxRate,
        Money $taxAmount,
        Money $lineTotal,
        int $position,
    ): self {
        return new self(
            id: $id,
            productId: $productId,
            sku: $sku,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            taxAmount: $taxAmount,
            lineTotal: $lineTotal,
            position: $position
        );
    }

    private static function calculateLineTotal(int $quantity, Money $unitPrice): Money
    {
        return $unitPrice->multiplyBy($quantity);
    }

    private static function calculateTaxAmount(Money $lineTotal, float $taxRate): Money
    {
        // Tax amount = line total * (tax rate / 100)
        return $lineTotal->multiplyBy($taxRate / 100.0);
    }

    /**
     * Gross total = line total + tax amount.
     */
    public function grossTotal(): Money
    {
        return $this->lineTotal->add($this->taxAmount);
    }

    public function id(): InvoiceLineId
    {
        return $this->id;
    }

    public function productId(): ?ProductId
    {
        return $this->productId;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function taxRate(): float
    {
        return $this->taxRate;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function lineTotal(): Money
    {
        return $this->lineTotal;
    }

    public function position(): int
    {
        return $this->position;
    }
}
