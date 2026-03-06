<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Application\Service\SegmentPricingService;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentPricingService::class)]
final class SegmentPricingServiceTest extends TestCase
{
    private PriceListRepositoryInterface $priceListRepository;
    private PromotionRepositoryInterface $promotionRepository;
    private SegmentPricingService $service;
    private TenantId $tenantId;
    private ProductId $productId;

    protected function setUp(): void
    {
        $this->priceListRepository = $this->createMock(PriceListRepositoryInterface::class);
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->service = new SegmentPricingService($this->priceListRepository, $this->promotionRepository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');
    }

    // -----------------------------------------------------------------------
    // calculatePrice — no price lists
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsBasePriceWhenNoPriceListsExist(): void
    {
        $this->priceListRepository->method('findValidForTenant')->willReturn([]);

        $basePrice = Money::of('100.00', 'USD');
        $result = $this->service->calculatePrice(
            $basePrice,
            $this->productId,
            CustomerSegment::vip(),
            $this->tenantId,
        );

        self::assertTrue($result->equals($basePrice));
    }

    // -----------------------------------------------------------------------
    // calculatePrice — segment rule match
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesSegmentDiscountWhenSegmentRuleExists(): void
    {
        $priceList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 20.0),
            100
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList]);

        $basePrice = Money::of('100.00', 'USD');
        $result = $this->service->calculatePrice(
            $basePrice,
            $this->productId,
            CustomerSegment::vip(),
            $this->tenantId,
        );

        // 20% off $100 = $80
        $expected = Money::of('80.00', 'USD');
        self::assertTrue($result->equals($expected));
    }

    #[Test]
    public function itUsesHighestPriorityPriceListSegmentRule(): void
    {
        $lowPriorityList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 10.0),
            50
        );
        $highPriorityList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 30.0),
            200
        );

        // Repository returns them in arbitrary order; service must sort by priority descending
        $this->priceListRepository
            ->method('findValidForTenant')
            ->willReturn([$lowPriorityList, $highPriorityList]);

        $basePrice = Money::of('100.00', 'USD');
        $result = $this->service->calculatePrice(
            $basePrice,
            $this->productId,
            CustomerSegment::vip(),
            $this->tenantId,
        );

        // High priority wins: 30% off $100 = $70
        $expected = Money::of('70.00', 'USD');
        self::assertTrue($result->equals($expected));
    }

    #[Test]
    public function itReturnsBasePriceWhenNoPriceListMatchesCustomerSegment(): void
    {
        // Price list has a VIP rule but customer is REGULAR
        $priceList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 20.0),
            100
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList]);

        $basePrice = Money::of('50.00', 'USD');
        $result = $this->service->calculatePrice(
            $basePrice,
            $this->productId,
            CustomerSegment::regular(), // no matching rule
            $this->tenantId,
        );

        self::assertTrue($result->equals($basePrice));
    }

    #[Test]
    public function itCalculatesPriceWithQuantityApplied(): void
    {
        $priceList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::wholesale(),
            Discount::fromTypeAndValue('percentage', 25.0),
            100
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList]);

        $basePrice = Money::of('100.00', 'USD');
        // Quantity = 2: total = 200, 25% off = 150, unit = 75
        $result = $this->service->calculatePrice(
            $basePrice,
            $this->productId,
            CustomerSegment::wholesale(),
            $this->tenantId,
            2
        );

        $expected = Money::of('75.00', 'USD');
        self::assertTrue($result->equals($expected));
    }

    // -----------------------------------------------------------------------
    // getApplicablePromotions
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoActivePromotions(): void
    {
        $this->promotionRepository
            ->method('findActivePromotions')
            ->with($this->tenantId)
            ->willReturn([]);

        $result = $this->service->getApplicablePromotions(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsPromotionsApplicableToAllSegments(): void
    {
        $promotion = $this->buildActivePromotion('All-Segment Promo');
        // No target segments means applicable to all

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$promotion]);

        $result = $this->service->getApplicablePromotions(
            CustomerSegment::regular(),
            $this->tenantId
        );

        self::assertCount(1, $result);
        self::assertSame('All-Segment Promo', $result[0]->name());
    }

    #[Test]
    public function itFiltersOutPromotionsNotApplicableToSegment(): void
    {
        $vipPromotion = $this->buildActivePromotion('VIP Promo');
        $vipPromotion->addTargetSegment(CustomerSegment::vip());

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$vipPromotion]);

        // Regular customer should NOT get VIP promotion
        $result = $this->service->getApplicablePromotions(
            CustomerSegment::regular(),
            $this->tenantId
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsPromotionsApplicableToMatchingSegment(): void
    {
        $vipPromotion = $this->buildActivePromotion('VIP Exclusive');
        $vipPromotion->addTargetSegment(CustomerSegment::vip());

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$vipPromotion]);

        $result = $this->service->getApplicablePromotions(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertCount(1, $result);
        self::assertSame('VIP Exclusive', $result[0]->name());
    }

    #[Test]
    public function itSortsPromotionsByPriorityDescending(): void
    {
        $lowPriority = $this->buildActivePromotionWithPriority('Low Priority', 50);
        $highPriority = $this->buildActivePromotionWithPriority('High Priority', 300);
        $midPriority = $this->buildActivePromotionWithPriority('Mid Priority', 150);

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$lowPriority, $highPriority, $midPriority]);

        $result = $this->service->getApplicablePromotions(
            CustomerSegment::regular(),
            $this->tenantId
        );

        self::assertCount(3, $result);
        self::assertSame('High Priority', $result[0]->name());
        self::assertSame('Mid Priority', $result[1]->name());
        self::assertSame('Low Priority', $result[2]->name());
    }

    #[Test]
    public function itFiltersOutExpiredPromotions(): void
    {
        $expiredPromotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: 'Expired Promo',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: 100,
            validFrom: new \DateTimeImmutable('2020-01-01'),
            validTo: new \DateTimeImmutable('2020-12-31'), // past
        );
        $expiredPromotion->activate();

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$expiredPromotion]);

        $result = $this->service->getApplicablePromotions(
            CustomerSegment::regular(),
            $this->tenantId
        );

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // hasExclusivePromotions
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFalseWhenNoPromotionsExistForSegment(): void
    {
        $this->promotionRepository->method('findActivePromotions')->willReturn([]);

        $result = $this->service->hasExclusivePromotions(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsTrueWhenSegmentHasExclusivePromotion(): void
    {
        $vipPromotion = $this->buildActivePromotion('VIP Exclusive Promo');
        $vipPromotion->addTargetSegment(CustomerSegment::vip()); // Has target segments = exclusive

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$vipPromotion]);

        $result = $this->service->hasExclusivePromotions(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenOnlyAllSegmentPromotionsExist(): void
    {
        $generalPromotion = $this->buildActivePromotion('General Promo');
        // No targetSegments = applies to all, not exclusive

        $this->promotionRepository
            ->method('findActivePromotions')
            ->willReturn([$generalPromotion]);

        $result = $this->service->hasExclusivePromotions(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // calculateTotalSegmentDiscount
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsZeroWhenNoPriceListsExist(): void
    {
        $this->priceListRepository->method('findValidForTenant')->willReturn([]);

        $result = $this->service->calculateTotalSegmentDiscount(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertSame(0.0, $result);
    }

    #[Test]
    public function itReturnsTotalPercentageDiscountAcrossPriceLists(): void
    {
        $priceList1 = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 10.0),
            100
        );
        $priceList2 = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 15.0),
            200
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList1, $priceList2]);

        $result = $this->service->calculateTotalSegmentDiscount(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertSame(25.0, $result);
    }

    #[Test]
    public function itCapsSegmentDiscountAt100Percent(): void
    {
        $priceList1 = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 60.0),
            100
        );
        $priceList2 = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 50.0),
            200
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList1, $priceList2]);

        $result = $this->service->calculateTotalSegmentDiscount(
            CustomerSegment::vip(),
            $this->tenantId
        );

        self::assertSame(100.0, $result);
    }

    #[Test]
    public function itReturnsZeroWhenNoSegmentRuleMatchesCustomer(): void
    {
        // Price list has a VIP rule, but customer is REGULAR
        $priceList = $this->buildActivePriceListWithSegmentRule(
            CustomerSegment::vip(),
            Discount::fromTypeAndValue('percentage', 20.0),
            100
        );

        $this->priceListRepository->method('findValidForTenant')->willReturn([$priceList]);

        $result = $this->service->calculateTotalSegmentDiscount(
            CustomerSegment::regular(),
            $this->tenantId
        );

        self::assertSame(0.0, $result);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildActivePriceListWithSegmentRule(
        CustomerSegment $segment,
        Discount $discount,
        int $priority,
    ): PriceList {
        $priceList = PriceList::reconstituteFromPersistence(
            id: PriceListId::generate(),
            tenantId: $this->tenantId,
            name: PriceListName::fromString('Test Price List'),
            priority: $priority,
            rules: [],
            segmentRules: [
                SegmentPricingRule::create($segment, $discount, $priority),
            ],
            validFrom: null,
            validTo: null,
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        return $priceList;
    }

    private function buildActivePromotion(string $name): Promotion
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: 100,
        );
        $promotion->activate();

        return $promotion;
    }

    private function buildActivePromotionWithPriority(string $name, int $priority): Promotion
    {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: $priority,
        );
        $promotion->activate();

        return $promotion;
    }
}
