<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\DTO\CartPriceCalculationResult;
use App\Pricing\Application\Query\GetCartPricing\GetCartPricingQuery;
use App\Pricing\Application\Query\GetCartPricing\GetCartPricingQueryHandler;
use App\Pricing\Application\Service\CartPricingServiceInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCartPricingQueryHandlerTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private CartPricingServiceInterface $cartPricingService;
    private GetCartPricingQueryHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartPricingService = $this->createMock(CartPricingServiceInterface::class);

        $this->handler = new GetCartPricingQueryHandler(
            $this->cartRepository,
            $this->cartPricingService
        );
    }

    public function testHandleReturnsCartPriceCalculationResult(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $cart = $this->createCart($cartId, $tenantId);

        $expectedResult = new CartPriceCalculationResult(
            cartId: $cartId->toString(),
            items: [],
            subtotal: Money::fromScalars(10000, 'USD'),
            totalDiscounts: Money::fromScalars(1000, 'USD'),
            grandTotal: Money::fromScalars(9000, 'USD'),
            cartLevelDiscounts: [],
            totalItemsCount: 1
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->with($cartId)
            ->willReturn($cart);

        $this->cartPricingService
            ->expects($this->once())
            ->method('calculateCartPricing')
            ->with($cart, [])
            ->willReturn($expectedResult);

        $query = new GetCartPricingQuery($cartId, $tenantId, []);
        $result = ($this->handler)($query);

        $this->assertSame($expectedResult, $result);
    }

    public function testHandleWithCouponCodes(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $cart = $this->createCart($cartId, $tenantId);
        $coupons = ['TESTCOUPON', 'WELCOME10'];

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($cart);

        $mockResult = new CartPriceCalculationResult(
            cartId: $cart->id()->toString(),
            items: [],
            subtotal: Money::fromScalars(10000, 'USD'),
            totalDiscounts: Money::fromScalars(0, 'USD'),
            grandTotal: Money::fromScalars(10000, 'USD'),
            cartLevelDiscounts: [],
            totalItemsCount: 1
        );

        $this->cartPricingService
            ->expects($this->once())
            ->method('calculateCartPricing')
            ->with($cart, $coupons)
            ->willReturn($mockResult);

        $query = new GetCartPricingQuery($cartId, $tenantId, $coupons);
        ($this->handler)($query);
    }

    public function testHandleThrowsExceptionWhenCartNotFound(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->with($cartId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cart with ID');

        $query = new GetCartPricingQuery($cartId, $tenantId, []);
        ($this->handler)($query);
    }

    private function createCart(CartId $cartId, TenantId $tenantId): Cart
    {
        $cart = Cart::create($cartId, $tenantId, null, SessionId::generate());
        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(10000, 'USD')
        );

        return Cart::reconstituteFromPersistence(
            $cart->id(),
            $cart->tenantId(),
            $cart->customerId(),
            $cart->sessionId(),
            $cart->status(),
            $cart->items(),
            $cart->createdAt(),
            $cart->updatedAt()
        );
    }
}
