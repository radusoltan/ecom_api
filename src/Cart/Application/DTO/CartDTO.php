<?php

declare(strict_types=1);

namespace App\Cart\Application\DTO;

use App\Cart\Domain\Model\Cart;

/**
 * CartDTO - Data Transfer Object for Cart.
 *
 * Used for read operations (queries)
 */
final readonly class CartDTO
{
    /**
     * @param array<int, array<string, mixed>> $items Array of CartItemDTO
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $customerId,
        public ?string $sessionId,
        public string $status,
        public array $items,
        public int $totalAmount,
        public string $totalCurrency,
        public int $itemCount,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromDomain(Cart $cart): self
    {
        $items = array_map(
            fn ($item) => [
                'id' => $item->id()->toString(),
                'productId' => $item->productId()->toString(),
                'variantId' => $item->variantId(),
                'quantity' => $item->quantity()->toInt(),
                'unitPriceAmount' => $item->unitPrice()->getAmount(),
                'unitPriceCurrency' => $item->unitPrice()->getCurrency()->getCurrencyCode(),
                'rowTotalAmount' => $item->rowTotal()->getAmount(),
            ],
            $cart->items()
        );

        $total = $cart->calculateTotal();

        return new self(
            id: $cart->id()->toString(),
            tenantId: $cart->tenantId()->toString(),
            customerId: $cart->customerId()?->toString(),
            sessionId: $cart->sessionId()?->toString(),
            status: $cart->status()->value(),
            items: $items,
            totalAmount: $total->getAmount(),
            totalCurrency: $total->getCurrency()->getCurrencyCode(),
            itemCount: $cart->getItemCount(),
            createdAt: $cart->createdAt()->format('Y-m-d H:i:s'),
            updatedAt: $cart->updatedAt()->format('Y-m-d H:i:s')
        );
    }
}
