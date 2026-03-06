<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Application\Query\GetAllPromotions\GetAllPromotionsQuery;
use App\Pricing\Application\Query\GetAllPromotions\GetAllPromotionsQueryHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAllPromotionsQueryHandler::class)]
final class GetAllPromotionsQueryHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private GetAllPromotionsQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new GetAllPromotionsQueryHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoPromotionsExist(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with($this->tenantId)
            ->willReturn([]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsMappedDtosForAllPromotions(): void
    {
        $promotions = [
            $this->buildPromotion('Summer Sale', 'cart_rule', 'percentage', 10.0, 100),
            $this->buildPromotion('Winter Deal', 'catalog_rule', 'fixed_amount', 25.0, 200),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with($this->tenantId)
            ->willReturn($promotions);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(PromotionDTO::class, $result);
    }

    #[Test]
    public function itMapsFirstPromotionFieldsCorrectly(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Exact Name Promo',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 30.0),
            priority: 500,
        );

        $this->repository->method('findAll')->willReturn([$promotion]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(1, $result);
        $dto = $result[0];
        self::assertSame($promotionId->toString(), $dto->id);
        self::assertSame($this->tenantId->toString(), $dto->tenantId);
        self::assertSame('Exact Name Promo', $dto->name);
        self::assertSame('cart_rule', $dto->type);
        self::assertSame('percentage', $dto->discountType);
        self::assertSame(30.0, $dto->discountValue);
        self::assertSame(500, $dto->priority);
        self::assertFalse($dto->isActive);
    }

    #[Test]
    public function itCallsRepositoryWithTenantId(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(self::callback(fn (TenantId $id) => $id->equals($this->tenantId)))
            ->willReturn([]);

        ($this->handler)(new GetAllPromotionsQuery($this->tenantId));
    }

    #[Test]
    public function itReturnsSingleElementArrayForOnePromotion(): void
    {
        $promotion = $this->buildPromotion('Solo Promo', 'catalog_rule', 'percentage', 5.0, 10);

        $this->repository->method('findAll')->willReturn([$promotion]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(1, $result);
        self::assertInstanceOf(PromotionDTO::class, $result[0]);
        self::assertSame('Solo Promo', $result[0]->name);
    }

    #[Test]
    public function itIncludesCouponPromotionsInResults(): void
    {
        $promotionId = PromotionId::generate();
        $couponPromotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Coupon Promotion',
            type: PromotionType::fromString('coupon'),
            discount: Discount::fromTypeAndValue('percentage', 15.0),
            priority: 100,
            couponCode: CouponCode::fromString('COUPON15'),
        );

        $this->repository->method('findAll')->willReturn([$couponPromotion]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(1, $result);
        self::assertSame('COUPON15', $result[0]->couponCode);
        self::assertSame('coupon', $result[0]->type);
    }

    #[Test]
    public function itPreservesOrderReturnedByRepository(): void
    {
        $first = $this->buildPromotion('First Promo', 'cart_rule', 'percentage', 5.0, 300);
        $second = $this->buildPromotion('Second Promo', 'catalog_rule', 'percentage', 10.0, 200);
        $third = $this->buildPromotion('Third Promo', 'cart_rule', 'fixed_amount', 20.0, 100);

        $this->repository->method('findAll')->willReturn([$first, $second, $third]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(3, $result);
        self::assertSame('First Promo', $result[0]->name);
        self::assertSame('Second Promo', $result[1]->name);
        self::assertSame('Third Promo', $result[2]->name);
    }

    #[Test]
    public function itIncludesActivePromotionsInResults(): void
    {
        $promotion = $this->buildPromotion('Active Promo', 'cart_rule', 'percentage', 20.0, 100);
        $promotion->activate();

        $this->repository->method('findAll')->willReturn([$promotion]);

        $result = ($this->handler)(new GetAllPromotionsQuery($this->tenantId));

        self::assertCount(1, $result);
        self::assertTrue($result[0]->isActive);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildPromotion(
        string $name,
        string $type,
        string $discountType,
        float $discountValue,
        int $priority,
    ): Promotion {
        return Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: PromotionType::fromString($type),
            discount: Discount::fromTypeAndValue($discountType, $discountValue),
            priority: $priority,
        );
    }
}
