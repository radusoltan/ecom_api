<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\Service\PromotionApplicationService;
use App\Pricing\Application\Service\PromotionStackingService;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PromotionApplicationServiceTest extends TestCase
{
    private PromotionRepositoryInterface&MockObject $promotionRepository;
    private PromotionStackingService $stackingService;
    private PromotionApplicationService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->stackingService = new PromotionStackingService();
        $this->service = new PromotionApplicationService($this->promotionRepository, $this->stackingService);
        $this->tenantId = TenantId::generate();
    }

    public function testApplyPromotionsWithNoActivePromotions(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([]);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal);

        $this->assertSame(10000, $result['finalPrice']->getAmount());
        $this->assertSame(0, $result['totalDiscount']->getAmount());
        $this->assertSame([], $result['appliedPromotions']);
        $this->assertNull($result['couponCode']);
    }

    public function testApplyCartRulePromotion(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');

        $cartRule = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Cart Rule 10%',
            PromotionType::cartRule(),
            Discount::percentage(10.0),
            100
        );
        $cartRule->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([$cartRule]);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal);

        // 10% off $100 = $10 discount, final price = $90
        $this->assertSame(9000, $result['finalPrice']->getAmount());
        $this->assertSame(1000, $result['totalDiscount']->getAmount());
        $this->assertCount(1, $result['appliedPromotions']);
        $this->assertSame('Cart Rule 10%', $result['appliedPromotions'][0]['name']);
        $this->assertSame('cart_rule', $result['appliedPromotions'][0]['type']);
    }

    public function testApplyPromotionWithMinPurchaseCondition(): void
    {
        $subtotal = Money::fromScalars(3000, 'USD'); // $30.00

        $promotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Min $50 Purchase',
            PromotionType::cartRule(),
            Discount::percentage(15.0),
            100,
            conditions: ['min_purchase' => 50] // Requires $50 minimum
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([$promotion]);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal);

        // Promotion not applied because subtotal < $50
        $this->assertSame(3000, $result['finalPrice']->getAmount());
        $this->assertSame(0, $result['totalDiscount']->getAmount());
        $this->assertCount(0, $result['appliedPromotions']);
    }

    public function testApplyPromotionWhenMinPurchaseConditionMet(): void
    {
        $subtotal = Money::fromScalars(6000, 'USD'); // $60.00

        $promotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Min $50 Purchase',
            PromotionType::cartRule(),
            Discount::percentage(15.0),
            100,
            conditions: ['min_purchase' => 50] // Requires $50 minimum
        );
        $promotion->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([$promotion]);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal);

        // 15% off $60 = $9 discount, final price = $51
        $this->assertSame(5100, $result['finalPrice']->getAmount());
        $this->assertSame(900, $result['totalDiscount']->getAmount());
        $this->assertCount(1, $result['appliedPromotions']);
    }

    public function testApplyValidCouponCode(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');
        $couponCode = 'SUMMER20';

        $coupon = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Summer Coupon',
            PromotionType::coupon(),
            Discount::fixedAmount(20.0),
            100,
            couponCode: CouponCode::fromString($couponCode)
        );
        $coupon->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->with($this->callback(fn($code) => $code->toString() === $couponCode), $this->tenantId)
            ->willReturn($coupon);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal, $couponCode);

        // $20 fixed discount, final price = $80
        $this->assertSame(8000, $result['finalPrice']->getAmount());
        $this->assertSame(2000, $result['totalDiscount']->getAmount());
        $this->assertCount(1, $result['appliedPromotions']);
        $this->assertSame('SUMMER20', $result['couponCode']);
    }

    public function testInvalidCouponCodeIsIgnored(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');
        $couponCode = 'INVALID';

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([]);

        $this->promotionRepository
            ->expects($this->once())
            ->method('findByCouponCode')
            ->willReturn(null); // Coupon not found

        $result = $this->service->applyPromotions($this->tenantId, $subtotal, $couponCode);

        // No discount applied
        $this->assertSame(10000, $result['finalPrice']->getAmount());
        $this->assertSame(0, $result['totalDiscount']->getAmount());
        $this->assertCount(0, $result['appliedPromotions']);
        $this->assertSame('INVALID', $result['couponCode']); // Coupon code still returned for reference
    }

    public function testStackMultiplePromotions(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');

        $cartRule = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Cart 10% Off',
            PromotionType::cartRule(),
            Discount::percentage(10.0),
            100
        );
        $cartRule->activate();

        $catalogRule = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Catalog 5% Off',
            PromotionType::catalogRule(),
            Discount::percentage(5.0),
            50
        );
        $catalogRule->activate();

        $this->promotionRepository
            ->expects($this->once())
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([$cartRule, $catalogRule]);

        $result = $this->service->applyPromotions($this->tenantId, $subtotal);

        // Cart: 10% off $100 = $90
        // Catalog: 5% off $90 = $85.50
        $this->assertSame(8550, $result['finalPrice']->getAmount());
        $this->assertSame(1450, $result['totalDiscount']->getAmount()); // $14.50 total
        $this->assertCount(2, $result['appliedPromotions']);
    }

    public function testPromotionWithCustomerSegmentCondition(): void
    {
        $subtotal = Money::fromScalars(10000, 'USD');

        $vipPromotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'VIP Discount',
            PromotionType::cartRule(),
            Discount::percentage(20.0),
            100,
            conditions: ['customer_segments' => ['vip', 'premium']]
        );
        $vipPromotion->activate();

        $this->promotionRepository
            ->expects($this->exactly(2))
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([$vipPromotion]);

        // Customer is in VIP segment
        $result = $this->service->applyPromotions(
            $this->tenantId,
            $subtotal,
            null,
            ['customer_segment' => 'vip']
        );

        $this->assertSame(8000, $result['finalPrice']->getAmount());
        $this->assertCount(1, $result['appliedPromotions']);

        // Customer is NOT in required segment
        $result2 = $this->service->applyPromotions(
            $this->tenantId,
            $subtotal,
            null,
            ['customer_segment' => 'regular']
        );

        $this->assertSame(10000, $result2['finalPrice']->getAmount());
        $this->assertCount(0, $result2['appliedPromotions']);
    }
}
