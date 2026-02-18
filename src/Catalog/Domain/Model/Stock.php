<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final class Stock
{
    private function __construct(
        private int $quantity,
        private bool $trackInventory,
        private bool $allowBackorder,
    ) {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Stock quantity cannot be negative');
        }
    }

    public static function create(
        int $quantity,
        bool $trackInventory = true,
        bool $allowBackorder = false,
    ): self {
        return new self($quantity, $trackInventory, $allowBackorder);
    }

    public function increase(int $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return new self(
            $this->quantity + $amount,
            $this->trackInventory,
            $this->allowBackorder
        );
    }

    public function decrease(int $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $newQuantity = $this->quantity - $amount;

        if (!$this->allowBackorder && $newQuantity < 0) {
            throw new \DomainException('Insufficient stock');
        }

        return new self(
            max(0, $newQuantity),
            $this->trackInventory,
            $this->allowBackorder
        );
    }

    public function isAvailable(int $requestedQuantity = 1): bool
    {
        if (!$this->trackInventory) {
            return true;
        }

        if ($this->allowBackorder) {
            return true;
        }

        return $this->quantity >= $requestedQuantity;
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->trackInventory && $this->quantity > 0 && $this->quantity <= $threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->trackInventory && $this->quantity <= 0;
    }

    // Getters
    public function quantity(): int
    {
        return $this->quantity;
    }

    public function trackInventory(): bool
    {
        return $this->trackInventory;
    }

    public function allowBackorder(): bool
    {
        return $this->allowBackorder;
    }
}
