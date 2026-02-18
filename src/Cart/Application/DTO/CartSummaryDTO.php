<?php

declare(strict_types=1);

namespace App\Cart\Application\DTO;

use App\Cart\Domain\Model\Cart;

/**
 * CartSummaryDTO - Lightweight DTO for Cart Summary.
 *
 * Contains only essential information (no detailed items)
 */
final readonly class CartSummaryDTO
{
    public function __construct(
        public string $id,
        public string $status,
        public int $itemCount,
        public int $totalAmount,
        public string $totalCurrency,
        public string $updatedAt,
    ) {
    }

    public static function fromDomain(Cart $cart): self
    {
        $total = $cart->calculateTotal();

        return new self(
            id: $cart->id()->toString(),
            status: $cart->status()->value(),
            itemCount: $cart->getItemCount(),
            totalAmount: $total->getAmount(),
            totalCurrency: $total->getCurrency()->getCurrencyCode(),
            updatedAt: $cart->updatedAt()->format('Y-m-d H:i:s')
        );
    }
}
