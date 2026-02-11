<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Command\ApplyCouponToCart\ApplyCouponToCartCommand;
use App\Pricing\Application\Command\ApplyCouponToCart\ApplyCouponToCartCommandHandler;
use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Application\Service\PromotionApplicatorInterface;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ApplyCouponToCartCommandHandlerTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private PromotionApplicatorInterface $promotionApplicator;
    private ApplyCouponToCartCommandHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->promotionApplicator = $this->createMock(PromotionApplicatorInterface::class);

        $this->handler = new ApplyCouponToCartCommandHandler(
            $this->cartRepository,
            $this->promotionApplicator
        );
    }

    public function testHandleAppliesCouponSuccessfully(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $cart = $this->createCart($cartId, $tenantId);
        $promotion = $this->createPromotion($tenantId);
        $couponCode = 'TESTCOUPON';

        $expectedDiscount = new AppliedDiscountDTO(
            id: $promotion->id()->toString(),
            type: 'promotion',
            name: $promotion->name(),
            amount: Money::fromScalars(1000, 'USD'),
            discountType: 'percentage',
            discountValue: 10.0,
            scope: 'cart'
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->with($cartId)
            ->willReturn($cart);

        $this->promotionApplicator
            ->expects($this->once())
            ->method('validateCoupon')
            ->with($couponCode, $tenantId)
            ->willReturn($promotion);

        $this->promotionApplicator
            ->expects($this->once())
            ->method('applyPromotion')
            ->with($promotion, $cart, $this->anything())
            ->willReturn($expectedDiscount);

        $command = new ApplyCouponToCartCommand($cartId, $tenantId, $couponCode);
        $result = ($this->handler)($command);

        $this->assertSame($expectedDiscount, $result);
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

        $command = new ApplyCouponToCartCommand($cartId, $tenantId, 'TESTCOUPON');
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenCouponInvalid(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $cart = $this->createCart($cartId, $tenantId);
        $couponCode = 'INVALID';

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($cart);

        $this->promotionApplicator
            ->expects($this->once())
            ->method('validateCoupon')
            ->with($couponCode, $tenantId)
            ->willThrowException(new \InvalidArgumentException('Invalid coupon code'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid coupon code');

        $command = new ApplyCouponToCartCommand($cartId, $tenantId, $couponCode);
        ($this->handler)($command);
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

    private function createPromotion(TenantId $tenantId): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $tenantId,
            'Test Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString('TESTCOUPON'),
            []
        );

        $promotion->activate();

        return $promotion;
    }
}
