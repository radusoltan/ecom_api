<?php

declare(strict_types=1);

namespace App\Cart\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * CartPriceCalculator Service.
 *
 * Responsibilities:
 * - Fetch current product price from Catalog/Pricing context
 * - Apply customer segment pricing (future)
 * - Apply price list logic (future)
 * - Return Money value object for cart items
 *
 * Integration Points:
 * - Catalog context: ProductRepository for base prices
 * - Pricing context: Future integration for segment/list prices
 *
 * Business Rules:
 * - Returns base product price
 * - Throws exception if product not found
 * - Future: Apply customer segment pricing
 * - Future: Apply price list rules
 *
 * Note: Promotion and coupon logic are P1 features (not in this service)
 */
final readonly class CartPriceCalculator
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Calculate the current price for a product/variant.
     *
     * @param ProductId       $productId  The product to price
     * @param string|null     $variantId  Optional variant ID
     * @param TenantId        $tenantId   Tenant context
     * @param CustomerId|null $customerId Optional customer for segment pricing
     *
     * @return Money The calculated price
     *
     * @throws \RuntimeException If product not found or price unavailable
     */
    public function calculateItemPrice(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
        ?CustomerId $customerId = null
    ): Money {
        // Fetch product from catalog
        $product = $this->productRepository->findById($productId, $tenantId);

        if (null === $product) {
            throw new \RuntimeException(sprintf('Product with ID "%s" not found', $productId->toString()));
        }

        // For now, return base price
        // TODO: Future enhancement - check for variant-specific pricing
        // TODO: Future enhancement - apply customer segment pricing
        // TODO: Future enhancement - apply price list rules

        $price = $product->price();

        // Price is always a Money object from Product domain model
        return $price;
    }

    /**
     * Validate that a price is still current (hasn't changed since added to cart).
     *
     * @param ProductId   $productId     The product to check
     * @param string|null $variantId     Optional variant ID
     * @param TenantId    $tenantId      Tenant context
     * @param Money       $cartItemPrice The price currently in the cart
     *
     * @return bool True if price is still current, false if changed
     */
    public function isPriceStillCurrent(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
        Money $cartItemPrice
    ): bool {
        try {
            $currentPrice = $this->calculateItemPrice($productId, $variantId, $tenantId);

            // Compare amounts and currencies
            return $currentPrice->getAmount() === $cartItemPrice->getAmount()
                && $currentPrice->getCurrency()->getCurrencyCode() === $cartItemPrice->getCurrency()->getCurrencyCode();
        } catch (\RuntimeException $e) {
            // Product not found or no price - consider price as changed
            return false;
        }
    }

    /**
     * Get price change details for notification purposes.
     *
     * @param ProductId   $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId    $tenantId  Tenant context
     * @param Money       $oldPrice  The old price from cart
     *
     * @return array{changed: bool, oldPrice: Money, newPrice: ?Money, difference: ?Money}
     */
    public function getPriceChangeDetails(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
        Money $oldPrice
    ): array {
        try {
            $newPrice = $this->calculateItemPrice($productId, $variantId, $tenantId);

            $changed = !$this->isPriceStillCurrent($productId, $variantId, $tenantId, $oldPrice);

            if ($changed) {
                // Calculate difference (can be positive or negative)
                $difference = $newPrice->subtract($oldPrice);

                return [
                    'changed' => true,
                    'oldPrice' => $oldPrice,
                    'newPrice' => $newPrice,
                    'difference' => $difference,
                ];
            }

            return [
                'changed' => false,
                'oldPrice' => $oldPrice,
                'newPrice' => $newPrice,
                'difference' => null,
            ];
        } catch (\RuntimeException $e) {
            return [
                'changed' => true,
                'oldPrice' => $oldPrice,
                'newPrice' => null,
                'difference' => null,
            ];
        }
    }
}
