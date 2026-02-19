<?php

declare(strict_types=1);

namespace App\Cart\Application\Query\GetCartTaxEstimate;

use App\Cart\Application\DTO\CartTaxEstimateDTO;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCartTaxEstimateQueryHandler
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private TaxCalculationService $taxCalculationService,
        private CustomerRepositoryInterface $customerRepository,
        private CacheService $cacheService,
    ) {
    }

    public function __invoke(GetCartTaxEstimateQuery $query): CartTaxEstimateDTO
    {
        $cartId = CartId::fromString($query->cartId);
        $tenantId = TenantId::fromString($query->tenantId);

        $cart = $this->cartRepository->findById($cartId);
        if (null === $cart) {
            throw CartNotFoundException::withId($query->cartId);
        }

        if (!$cart->isActive()) {
            throw new \DomainException(sprintf('Cart "%s" is not active', $query->cartId));
        }

        // Determine currency from cart items (default to EUR)
        $currency = 'EUR';
        $items = $cart->items();
        if ([] !== $items) {
            $currency = $items[0]->unitPrice()->getCurrency()->getCurrencyCode();
        }

        // Empty cart → zero totals
        if ($cart->isEmpty()) {
            return $this->buildEmptyCartResponse($query->cartId, $currency, $query->countryCode);
        }

        // Resolve jurisdiction
        $jurisdiction = $this->resolveJurisdiction($query, $cart, $tenantId);
        if (null === $jurisdiction) {
            return $this->buildNoAddressResponse($query->cartId, $cart, $currency);
        }

        // Use cache for the computation
        $cacheKey = $this->buildCacheKey($query, $tenantId);

        return $this->cacheService->get(
            $cacheKey,
            fn () => $this->calculateTaxEstimate($query->cartId, $cart, $jurisdiction, $tenantId, $currency),
            self::CACHE_TTL,
            ['cart-tax', sprintf('tenant:%s', $tenantId->toString())],
        );
    }

    private function calculateTaxEstimate(
        string $cartId,
        Cart $cart,
        TaxJurisdiction $jurisdiction,
        TenantId $tenantId,
        string $currency,
    ): CartTaxEstimateDTO {
        // Build line items for TaxCalculationService
        $lineItems = [];
        foreach ($cart->items() as $item) {
            $lineItems[] = [
                'amountInCents' => $item->unitPrice()->getAmount(),
                'quantity' => $item->quantity()->toInt(),
            ];
        }

        $taxResult = $this->taxCalculationService->calculateOrderTax($lineItems, $jurisdiction, $tenantId);

        // Build per-item breakdown
        $itemBreakdown = [];
        foreach ($cart->items() as $item) {
            $rowTotal = $item->rowTotal()->getAmount();
            $itemTax = $taxResult['taxRate'] > 0
                ? (int) round($rowTotal * $taxResult['taxRate'] / 100)
                : 0;

            $itemBreakdown[] = [
                'productId' => $item->productId()->toString(),
                'variantId' => $item->variantId(),
                'quantity' => $item->quantity()->toInt(),
                'unitPrice' => $item->unitPrice()->getAmount(),
                'rowTotal' => $rowTotal,
                'taxAmount' => $itemTax,
            ];
        }

        return new CartTaxEstimateDTO(
            cartId: $cartId,
            subtotal: $taxResult['subtotal'],
            currency: $currency,
            taxEstimateAvailable: true,
            taxAmount: $taxResult['taxAmount'],
            total: $taxResult['total'],
            taxRate: $taxResult['taxRate'],
            jurisdiction: $taxResult['jurisdiction'],
            taxRuleName: $taxResult['taxRuleName'],
            message: null,
            itemBreakdown: $itemBreakdown,
        );
    }

    private function resolveJurisdiction(GetCartTaxEstimateQuery $query, Cart $cart, TenantId $tenantId): ?TaxJurisdiction
    {
        // 1. Use explicitly provided country/region
        if (null !== $query->countryCode) {
            return null !== $query->regionCode
                ? TaxJurisdiction::fromCountryAndRegion($query->countryCode, $query->regionCode)
                : TaxJurisdiction::fromCountry($query->countryCode);
        }

        // 2. Look up customer's default shipping address
        if (null === $cart->customerId()) {
            return null;
        }

        $customer = $this->customerRepository->findById(
            CustomerId::fromString($cart->customerId()->toString()),
            $tenantId,
        );
        if (null === $customer) {
            return null;
        }

        $defaultAddress = $customer->getDefaultShippingAddress();
        if (null === $defaultAddress) {
            return null;
        }

        $countryCode = $defaultAddress->country;
        $regionCode = $defaultAddress->state;

        // TaxJurisdiction expects 2-3 char region codes; CustomerAddress.state can be a full name
        $isValidRegionCode = null !== $regionCode
            && '' !== $regionCode
            && strlen($regionCode) >= 2
            && strlen($regionCode) <= 3;

        return $isValidRegionCode
            ? TaxJurisdiction::fromCountryAndRegion($countryCode, $regionCode)
            : TaxJurisdiction::fromCountry($countryCode);
    }

    private function buildEmptyCartResponse(string $cartId, string $currency, ?string $countryCode): CartTaxEstimateDTO
    {
        return new CartTaxEstimateDTO(
            cartId: $cartId,
            subtotal: 0,
            currency: $currency,
            taxEstimateAvailable: true,
            taxAmount: 0,
            total: 0,
            taxRate: 0,
            jurisdiction: $countryCode,
            taxRuleName: null,
            message: null,
            itemBreakdown: [],
        );
    }

    private function buildNoAddressResponse(string $cartId, Cart $cart, string $currency): CartTaxEstimateDTO
    {
        $subtotal = $cart->calculateTotal()->getAmount();

        return new CartTaxEstimateDTO(
            cartId: $cartId,
            subtotal: $subtotal,
            currency: $currency,
            taxEstimateAvailable: false,
            taxAmount: 0,
            total: $subtotal,
            taxRate: 0,
            jurisdiction: null,
            taxRuleName: null,
            message: 'Tax will be calculated at checkout when shipping address is provided',
            itemBreakdown: [],
        );
    }

    private function buildCacheKey(GetCartTaxEstimateQuery $query, TenantId $tenantId): string
    {
        $country = $query->countryCode ?? 'auto';
        $region = $query->regionCode ?? 'none';

        return $this->cacheService->tenantKey(
            $tenantId->toString(),
            sprintf('cart-tax:%s:%s:%s', $query->cartId, $country, $region),
        );
    }
}
