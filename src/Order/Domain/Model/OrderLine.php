<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Order Line Value Object.
 *
 * Represents a single line item in an order.
 *
 * Business Rules:
 * - quantity must be > 0
 * - unitPrice must be > 0
 * - total is calculated as quantity * unitPrice
 */
final readonly class OrderLine
{
    private function __construct(
        private ProductId $productId,
        private string $productName,
        private int $quantity,
        private Money $unitPrice
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Order line quantity must be greater than 0');
        }

        if ($unitPrice->getAmount() <= 0) {
            throw new \InvalidArgumentException('Order line unit price must be greater than 0');
        }
    }

    public static function create(
        ProductId $productId,
        string $productName,
        int $quantity,
        Money $unitPrice
    ): self {
        return new self($productId, $productName, $quantity, $unitPrice);
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function withQuantity(int $newQuantity): self
    {
        return new self($this->productId, $this->productName, $newQuantity, $this->unitPrice);
    }
}
