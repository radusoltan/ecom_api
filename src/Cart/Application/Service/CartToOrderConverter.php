<?php

declare(strict_types=1);

namespace App\Cart\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Domain\Model\OrderId;

/**
 * CartToOrderConverter Service.
 *
 * Converts a Cart aggregate to a PlaceOrderCommand for order placement.
 *
 * Business Rules:
 * - Cart must be active
 * - Cart must have at least 1 item
 * - Guest email is required for guest checkouts
 * - Shipping and billing addresses are required
 * - Coupon code is optional
 *
 * Mapping:
 * - CartItem → OrderLine (productId, productName, quantity, unitPrice)
 * - Cart total → Order subtotal (calculated in PlaceOrderCommandHandler)
 * - Guest email → Order customerEmail
 * - Address data → Order shipping/billing addresses
 *
 * Integration Points:
 * - Order Context: PlaceOrderCommand
 * - Cart Context: Cart aggregate
 */
final readonly class CartToOrderConverter
{
    /**
     * Convert Cart to PlaceOrderCommand.
     *
     * @param Cart                 $cart             The cart to convert
     * @param string               $customerEmail    Customer email (from session or guest input)
     * @param array<string, mixed> $shippingAddress  Shipping address ['street', 'city', 'state', 'postalCode', 'country']
     * @param array<string, mixed> $billingAddress   Billing address ['street', 'city', 'state', 'postalCode', 'country']
     * @param string|null          $couponCode       Optional coupon code
     * @param array<string, mixed> $promotionContext Optional promotion context (customer segment, categories, etc.)
     *
     * @throws \InvalidArgumentException If cart is invalid or missing required data
     */
    public function convert(
        Cart $cart,
        string $customerEmail,
        array $shippingAddress,
        array $billingAddress,
        ?string $couponCode = null,
        array $promotionContext = []
    ): PlaceOrderCommand {
        // Validate cart status
        if (!$cart->isActive()) {
            throw new \InvalidArgumentException('Cannot convert inactive cart to order');
        }

        // Validate cart has items
        if ($cart->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        // Validate customer email
        if (empty(trim($customerEmail))) {
            throw new \InvalidArgumentException('Customer email is required for order placement');
        }

        // Validate addresses
        $this->validateAddress($shippingAddress, 'Shipping');
        $this->validateAddress($billingAddress, 'Billing');

        // Map cart items to order lines
        $orderLines = [];
        foreach ($cart->items() as $cartItem) {
            $orderLines[] = [
                'productId' => $cartItem->productId()->toString(),
                'productName' => $this->getProductName($cartItem->productId()->toString()), // Placeholder - will be fetched from Catalog
                'quantity' => $cartItem->quantity()->value(),
                'unitPriceAmount' => $cartItem->unitPrice()->getAmount(),
                'unitPriceCurrency' => $cartItem->unitPrice()->getCurrency(),
            ];
        }

        // Generate new OrderId
        $orderId = OrderId::generate()->toString();

        // Create PlaceOrderCommand
        return new PlaceOrderCommand(
            orderId: $orderId,
            tenantId: $cart->tenantId()->toString(),
            customerEmail: trim($customerEmail),
            lines: $orderLines,
            shippingAddress: $shippingAddress,
            billingAddress: $billingAddress,
            couponCode: $couponCode,
            promotionContext: $promotionContext
        );
    }

    /**
     * Validate address has all required fields.
     *
     * @param array<string, mixed> $address
     * @param string               $type    Address type (for error messages)
     *
     * @throws \InvalidArgumentException If address is invalid
     */
    private function validateAddress(array $address, string $type): void
    {
        $requiredFields = ['street', 'city', 'state', 'postalCode', 'country'];

        foreach ($requiredFields as $field) {
            if (!isset($address[$field]) || (is_string($address[$field]) && '' === trim($address[$field]))) {
                throw new \InvalidArgumentException(sprintf('%s address is missing required field: %s', $type, $field));
            }
        }
    }

    /**
     * Get product name from product ID.
     *
     * Placeholder method - in real implementation, this would query the Catalog context
     * For now, we return a default name and let PlaceOrderCommandHandler fetch the real name
     */
    private function getProductName(string $productId): string
    {
        // Placeholder - product name will be fetched by PlaceOrderCommandHandler from Catalog
        return 'Product '.substr($productId, 0, 8);
    }
}
