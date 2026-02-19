<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Query;

use App\Cart\Application\DTO\CartTaxEstimateDTO;
use App\Cart\Application\Query\GetCartTaxEstimate\GetCartTaxEstimateQuery;
use App\Cart\Application\Query\GetCartTaxEstimate\GetCartTaxEstimateQueryHandler;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Tax\Domain\Service\TaxCalculationService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetCartTaxEstimateQueryHandlerTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private TaxCalculationService $taxCalculationService;
    private CustomerRepositoryInterface $customerRepository;
    private CacheService $cacheService;
    private GetCartTaxEstimateQueryHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->taxCalculationService = $this->createMock(TaxCalculationService::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);

        // Create a pass-through CacheService (always executes callback)
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            static function (string $key, callable $callback) {
                $item = new class implements ItemInterface {
                    public function getKey(): string
                    {
                        return 'test';
                    }

                    public function get(): mixed
                    {
                        return null;
                    }

                    public function isHit(): bool
                    {
                        return false;
                    }

                    public function set(mixed $value): static
                    {
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }

                    /** @param string[] $tags */
                    public function tag(string|iterable $tags): static
                    {
                        return $this;
                    }

                    public function getMetadata(): array
                    {
                        return [];
                    }
                };

                return $callback($item);
            }
        );
        $this->cacheService = new CacheService($cache, new \Psr\Log\NullLogger());

        $this->handler = new GetCartTaxEstimateQueryHandler(
            $this->cartRepository,
            $this->taxCalculationService,
            $this->customerRepository,
            $this->cacheService,
        );
    }

    // =============================================
    // Cart Not Found
    // =============================================

    public function testItThrowsWhenCartNotFound(): void
    {
        $this->cartRepository->method('findById')->willReturn(null);

        $this->expectException(CartNotFoundException::class);

        ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: CartId::generate()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'RO',
        ));
    }

    // =============================================
    // Cart Expired / Not Active
    // =============================================

    public function testItThrowsWhenCartIsNotActive(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsConverted();

        $this->cartRepository->method('findById')->willReturn($cart);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not active/');

        ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'RO',
        ));
    }

    // =============================================
    // Empty Cart
    // =============================================

    public function testItReturnsZeroTotalsForEmptyCart(): void
    {
        $cart = $this->createActiveCart();
        $this->cartRepository->method('findById')->willReturn($cart);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'RO',
        ));

        $this->assertInstanceOf(CartTaxEstimateDTO::class, $result);
        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(0, $result->subtotal);
        $this->assertSame(0, $result->taxAmount);
        $this->assertSame(0, $result->total);
        $this->assertSame('RO', $result->jurisdiction);
        $this->assertSame([], $result->itemBreakdown);
    }

    // =============================================
    // Domestic Tax (RO → 19% TVA)
    // =============================================

    public function testItCalculatesDomesticRomanianTax(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 2999, 'currency' => 'EUR', 'quantity' => 2],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 5998,
            'taxAmount' => 1140,
            'total' => 7138,
            'taxRate' => 19.0,
            'jurisdiction' => 'RO',
            'taxRuleId' => 'rule-1',
            'taxRuleName' => 'Romania Standard VAT',
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'RO',
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(5998, $result->subtotal);
        $this->assertSame(1140, $result->taxAmount);
        $this->assertSame(7138, $result->total);
        $this->assertSame(19.0, $result->taxRate);
        $this->assertSame('RO', $result->jurisdiction);
        $this->assertSame('Romania Standard VAT', $result->taxRuleName);
        $this->assertNull($result->message);

        // Verify item breakdown
        $this->assertCount(1, $result->itemBreakdown);
        $this->assertSame(5998, $result->itemBreakdown[0]['rowTotal']);
        $this->assertSame(1140, $result->itemBreakdown[0]['taxAmount']);
    }

    // =============================================
    // Intra-EU Tax (DE address)
    // =============================================

    public function testItCalculatesIntraEuTaxForGermany(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 10000, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 10000,
            'taxAmount' => 1900,
            'total' => 11900,
            'taxRate' => 19.0,
            'jurisdiction' => 'DE',
            'taxRuleId' => 'rule-de',
            'taxRuleName' => 'Germany Standard VAT',
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'DE',
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(10000, $result->subtotal);
        $this->assertSame(1900, $result->taxAmount);
        $this->assertSame(11900, $result->total);
        $this->assertSame(19.0, $result->taxRate);
        $this->assertSame('DE', $result->jurisdiction);
    }

    // =============================================
    // Extra-EU Tax (US → 0%)
    // =============================================

    public function testItReturnsZeroTaxForExtraEuAddress(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 5000, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 5000,
            'taxAmount' => 0,
            'total' => 5000,
            'taxRate' => 0.0,
            'jurisdiction' => 'US',
            'taxRuleId' => null,
            'taxRuleName' => null,
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'US',
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(5000, $result->subtotal);
        $this->assertSame(0, $result->taxAmount);
        $this->assertSame(5000, $result->total);
        $this->assertSame(0.0, $result->taxRate);
    }

    // =============================================
    // No Address — Guest Cart (no customer)
    // =============================================

    public function testItReturnsUnavailableWhenNoAddressAndNoCustomer(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 2999, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            // No countryCode provided, guest cart (no customerId)
        ));

        $this->assertFalse($result->taxEstimateAvailable);
        $this->assertSame(2999, $result->subtotal);
        $this->assertSame(0, $result->taxAmount);
        $this->assertSame(2999, $result->total);
        $this->assertNull($result->jurisdiction);
        $this->assertSame('Tax will be calculated at checkout when shipping address is provided', $result->message);
        $this->assertSame([], $result->itemBreakdown);
    }

    // =============================================
    // Customer Default Address → Auto-resolve
    // =============================================

    public function testItResolvesJurisdictionFromCustomerDefaultAddress(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();

        $cart = $this->createCartWithItemsForCustomer($customerId, [
            ['price' => 4000, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe',
        );
        $customer->addAddress(CustomerAddress::create(
            CustomerAddressId::generate(),
            '123 Main St',
            null,
            'Bucharest',
            'BU',
            '010001',
            'RO',
            AddressType::SHIPPING,
            true, // isDefaultShipping
            false,
        ));

        $this->customerRepository->method('findById')->willReturn($customer);

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 4000,
            'taxAmount' => 760,
            'total' => 4760,
            'taxRate' => 19.0,
            'jurisdiction' => 'RO',
            'taxRuleId' => 'rule-ro',
            'taxRuleName' => 'Romania Standard VAT',
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            // No countryCode — should auto-resolve from customer address
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(760, $result->taxAmount);
        $this->assertSame('RO', $result->jurisdiction);
    }

    // =============================================
    // Query Params Override Customer Address
    // =============================================

    public function testItUsesQueryParamsOverCustomerDefaultAddress(): void
    {
        $customerId = CustomerId::generate();
        $cart = $this->createCartWithItemsForCustomer($customerId, [
            ['price' => 4000, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        // Customer has RO address, but we pass DE as query param
        $this->customerRepository->expects($this->never())->method('findById');

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 4000,
            'taxAmount' => 760,
            'total' => 4760,
            'taxRate' => 19.0,
            'jurisdiction' => 'DE',
            'taxRuleId' => 'rule-de',
            'taxRuleName' => 'Germany Standard VAT',
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'DE', // Explicit override
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame('DE', $result->jurisdiction);
    }

    // =============================================
    // Mixed Items (different products, same tax rate)
    // =============================================

    public function testItCalculatesTaxForMultipleItems(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 1999, 'currency' => 'EUR', 'quantity' => 2],
            ['price' => 4999, 'currency' => 'EUR', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        // subtotal = 1999*2 + 4999*1 = 8997
        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 8997,
            'taxAmount' => 1709,
            'total' => 10706,
            'taxRate' => 19.0,
            'jurisdiction' => 'RO',
            'taxRuleId' => 'rule-ro',
            'taxRuleName' => 'Romania Standard VAT',
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'RO',
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame(8997, $result->subtotal);
        $this->assertSame(1709, $result->taxAmount);
        $this->assertSame(10706, $result->total);
        $this->assertCount(2, $result->itemBreakdown);

        // Verify item breakdown adds up
        $breakdownTaxTotal = array_sum(array_column($result->itemBreakdown, 'taxAmount'));
        // Per-item tax is distributed proportionally; may differ from total by ±1 due to rounding
        $this->assertEqualsWithDelta($result->taxAmount, $breakdownTaxTotal, 2);
    }

    // =============================================
    // Country With Region Code
    // =============================================

    public function testItHandlesCountryWithRegionCode(): void
    {
        $cart = $this->createCartWithItems([
            ['price' => 5000, 'currency' => 'USD', 'quantity' => 1],
        ]);
        $this->cartRepository->method('findById')->willReturn($cart);

        $this->taxCalculationService->method('calculateOrderTax')->willReturn([
            'subtotal' => 5000,
            'taxAmount' => 0,
            'total' => 5000,
            'taxRate' => 0.0,
            'jurisdiction' => 'US-CA',
            'taxRuleId' => null,
            'taxRuleName' => null,
        ]);

        $result = ($this->handler)(new GetCartTaxEstimateQuery(
            cartId: $cart->id()->toString(),
            tenantId: self::TENANT_ID,
            countryCode: 'US',
            regionCode: 'CA',
        ));

        $this->assertTrue($result->taxEstimateAvailable);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('US-CA', $result->jurisdiction);
    }

    // =============================================
    // DTO toArray
    // =============================================

    public function testDtoToArrayReturnsCorrectStructure(): void
    {
        $dto = new CartTaxEstimateDTO(
            cartId: 'test-cart-id',
            subtotal: 5998,
            currency: 'EUR',
            taxEstimateAvailable: true,
            taxAmount: 1140,
            total: 7138,
            taxRate: 19.0,
            jurisdiction: 'RO',
            taxRuleName: 'Romania Standard VAT',
            message: null,
            itemBreakdown: [
                ['productId' => 'p1', 'variantId' => null, 'quantity' => 2, 'unitPrice' => 2999, 'rowTotal' => 5998, 'taxAmount' => 1140],
            ],
        );

        $array = $dto->toArray();

        $this->assertSame('test-cart-id', $array['cartId']);
        $this->assertSame(5998, $array['subtotal']);
        $this->assertTrue($array['taxEstimateAvailable']);
        $this->assertSame(1140, $array['taxAmount']);
        $this->assertSame(7138, $array['total']);
        $this->assertSame(19.0, $array['taxRate']);
        $this->assertSame('RO', $array['jurisdiction']);
        $this->assertCount(1, $array['itemBreakdown']);
    }

    // =============================================
    // Helpers
    // =============================================

    private function createActiveCart(?CustomerId $customerId = null): Cart
    {
        return Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            $customerId,
            SessionId::generate(),
        );
    }

    /**
     * @param array<int, array{price: int, currency: string, quantity: int}> $items
     */
    private function createCartWithItems(array $items): Cart
    {
        $cart = $this->createActiveCart();

        foreach ($items as $item) {
            $cart->addItem(
                ProductId::generate(),
                null,
                Quantity::fromInt($item['quantity']),
                Money::fromScalars($item['price'], $item['currency']),
            );
        }

        return $cart;
    }

    /**
     * @param array<int, array{price: int, currency: string, quantity: int}> $items
     */
    private function createCartWithItemsForCustomer(CustomerId $customerId, array $items): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            $customerId,
            SessionId::generate(),
        );

        foreach ($items as $item) {
            $cart->addItem(
                ProductId::generate(),
                null,
                Quantity::fromInt($item['quantity']),
                Money::fromScalars($item['price'], $item['currency']),
            );
        }

        return $cart;
    }
}
