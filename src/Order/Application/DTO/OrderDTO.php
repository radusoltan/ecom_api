<?php

declare(strict_types=1);

namespace App\Order\Application\DTO;

final readonly class OrderDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $customerEmail,
        public string $status,
        public array $lines, // Array of OrderLineDTO
        public array $shippingAddress,
        public array $billingAddress,
        public int $totalAmount,
        public string $totalCurrency,
        public array $appliedPromotions,
        public ?int $discountAmount,
        public ?string $discountCurrency,
        public ?string $couponCode,
        public ?int $taxAmount,
        public ?string $taxCurrency,
        public ?string $taxJurisdiction,
        public ?string $taxRuleId,
        public float $taxRate,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDomain(\App\Order\Domain\Model\Order $order): self
    {
        return new self(
            id: $order->id()->toString(),
            tenantId: $order->tenantId()->toString(),
            customerEmail: $order->customerEmail(),
            status: $order->status()->value(),
            lines: array_map(
                fn ($line) => [
                    'productId' => $line->productId()->toString(),
                    'productName' => $line->productName(),
                    'quantity' => $line->quantity(),
                    'unitPriceAmount' => $line->unitPrice()->getAmount(),
                    'unitPriceCurrency' => $line->unitPrice()->getCurrency()->getCurrencyCode(),
                    'totalAmount' => $line->total()->getAmount(),
                ],
                $order->lines()
            ),
            shippingAddress: [
                'street' => $order->shippingAddress()->street(),
                'city' => $order->shippingAddress()->city(),
                'state' => $order->shippingAddress()->state(),
                'postalCode' => $order->shippingAddress()->postalCode(),
                'country' => $order->shippingAddress()->country(),
            ],
            billingAddress: [
                'street' => $order->billingAddress()->street(),
                'city' => $order->billingAddress()->city(),
                'state' => $order->billingAddress()->state(),
                'postalCode' => $order->billingAddress()->postalCode(),
                'country' => $order->billingAddress()->country(),
            ],
            totalAmount: $order->total()->getAmount(),
            totalCurrency: $order->total()->getCurrency()->getCurrencyCode(),
            appliedPromotions: $order->appliedPromotions(),
            discountAmount: $order->discountAmount()?->getAmount(),
            discountCurrency: $order->discountAmount()?->getCurrency()->getCurrencyCode(),
            couponCode: $order->couponCode(),
            taxAmount: $order->taxAmount()?->getAmount(),
            taxCurrency: $order->taxAmount()?->getCurrency()->getCurrencyCode(),
            taxJurisdiction: $order->taxJurisdiction(),
            taxRuleId: $order->taxRuleId(),
            taxRate: $order->taxRate(),
            createdAt: $order->createdAt()->format('Y-m-d H:i:s'),
            updatedAt: $order->updatedAt()->format('Y-m-d H:i:s')
        );
    }
}
