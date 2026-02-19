<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

final readonly class PlaceOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $tenantId,
        public string $customerEmail,
        public array $lines, // Array of ['productId', 'productName', 'quantity', 'unitPrice']
        public array $shippingAddress, // ['street', 'city', 'state', 'postalCode', 'country']
        public array $billingAddress, // ['street', 'city', 'state', 'postalCode', 'country']
        public ?string $couponCode = null, // Optional coupon code
        public array $promotionContext = [], // Context for promotion evaluation (customer segment, categories, etc.)
        public ?string $vatNumber = null, // B2B VAT number for reverse charge
        public ?string $sellerCountryCode = null, // Seller's EU country code (from tenant config)
    ) {
    }
}
