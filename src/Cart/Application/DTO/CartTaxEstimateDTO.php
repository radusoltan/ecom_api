<?php

declare(strict_types=1);

namespace App\Cart\Application\DTO;

/**
 * Cart Tax Estimate DTO.
 *
 * Read-only DTO for tax estimation results.
 * All monetary amounts are in cents (minor currency units).
 *
 * @phpstan-type ItemBreakdown array{productId: string, variantId: string|null, quantity: int, unitPrice: int, rowTotal: int, taxAmount: int}
 */
final readonly class CartTaxEstimateDTO
{
    /**
     * @param array<int, array<string, mixed>> $itemBreakdown Per-item tax breakdown
     */
    public function __construct(
        public string $cartId,
        public int $subtotal,
        public string $currency,
        public bool $taxEstimateAvailable,
        public int $taxAmount,
        public int $total,
        public float $taxRate,
        public ?string $jurisdiction,
        public ?string $taxRuleName,
        public ?string $message,
        public array $itemBreakdown,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cartId' => $this->cartId,
            'subtotal' => $this->subtotal,
            'currency' => $this->currency,
            'taxEstimateAvailable' => $this->taxEstimateAvailable,
            'taxAmount' => $this->taxAmount,
            'total' => $this->total,
            'taxRate' => $this->taxRate,
            'jurisdiction' => $this->jurisdiction,
            'taxRuleName' => $this->taxRuleName,
            'message' => $this->message,
            'itemBreakdown' => $this->itemBreakdown,
        ];
    }
}
