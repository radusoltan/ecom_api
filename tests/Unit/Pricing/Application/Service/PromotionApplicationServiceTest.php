<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\Service\PromotionApplicationService;
use App\Pricing\Application\Service\PromotionStackingServiceInterface;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PromotionApplicationServiceTest extends TestCase
{
    private PromotionRepositoryInterface $promotionRepository;
    private PromotionStackingServiceInterface $stackingService;
    private LoggerInterface $logger;
    private PromotionApplicationService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->stackingService = $this->createMock(PromotionStackingServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new PromotionApplicationService(
            $this->promotionRepository,
            $this->stackingService,
            $this->logger
        );

        $this->tenantId = TenantId::generate();
    }

    public function testApplyValidCouponCode(): void
    {
        $subtotal = Money::of('50.00', 'EUR');
        $couponPromotion = $this->createPromotion(
            name: 'Welcome 10%',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: CouponCode::fromString('WELCOME10')
        );

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([]);

        $this->promotionRepository
            ->method('findByCouponCode')
            ->willReturn($couponPromotion);

        $expectedFinalPrice = Money::of('45.00', 'EUR');
        $expectedDiscount = Money::of('5.00', 'EUR');

        $this->stackingService
            ->method('calculatePriceWithPromotions')
            ->willReturn([
                'finalPrice' => $expectedFinalPrice,
                'appliedPromotions' => [$couponPromotion],
                'totalDiscount' => $expectedDiscount,
            ]);

        $result = $this->service->applyPromotions(
            tenantId: $this->tenantId,
            subtotal: $subtotal,
            couponCode: 'WELCOME10'
        );

        $this->assertTrue($result['finalPrice']->equals($expectedFinalPrice));
        $this->assertSame('WELCOME10', $result['couponCode']);
        $this->assertSame(1, count($result['appliedPromotions']));
    }

    public function testApplyInvalidCouponCode(): void
    {
        $subtotal = Money::of('50.00', 'EUR');

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([]);

        $this->promotionRepository
            ->method('findByCouponCode')
            ->willReturn(null);

        $this->stackingService
            ->method('calculatePriceWithPromotions')
            ->willReturn([
                'finalPrice' => $subtotal,
                'appliedPromotions' => [],
                'totalDiscount' => Money::of('0.00', 'EUR'),
            ]);

        $result = $this->service->applyPromotions(
            tenantId: $this->tenantId,
            subtotal: $subtotal,
            couponCode: 'INVALID'
        );

        $this->assertTrue($result['finalPrice']->equals($subtotal));
        $this->assertSame(0, count($result['appliedPromotions']));
        $this->assertSame('INVALID', $result['couponCode']);
    }

    private function createPromotion(
        string $name,
        PromotionType $type,
        Discount $discount,
        int $priority = 100,
        ?CouponCode $couponCode = null,
        array $conditions = []
    ): Promotion {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: $type,
            discount: $discount,
            priority: $priority,
            couponCode: $couponCode,
            conditions: $conditions
        );

        $promotion->activate();

        return $promotion;
    }
}
