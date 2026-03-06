<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQuery;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQueryHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetPromotionByIdQueryHandler::class)]
final class GetPromotionByIdQueryHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private GetPromotionByIdQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new GetPromotionByIdQueryHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsPromotionDtoWhenFound(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildPromotion($promotionId, 'Flash Promo');

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($promotionId, $this->tenantId)
            ->willReturn($promotion);

        $query = new GetPromotionByIdQuery($promotionId, $this->tenantId);

        $result = ($this->handler)($query);

        self::assertInstanceOf(PromotionDTO::class, $result);
        self::assertSame($promotionId->toString(), $result->id);
        self::assertSame($this->tenantId->toString(), $result->tenantId);
        self::assertSame('Flash Promo', $result->name);
        self::assertSame('cart_rule', $result->type);
        self::assertSame('percentage', $result->discountType);
        self::assertSame(15.0, $result->discountValue);
        self::assertSame(100, $result->priority);
        self::assertFalse($result->isActive);
        self::assertNull($result->couponCode);
        self::assertSame([], $result->conditions);
        self::assertNull($result->validFrom);
        self::assertNull($result->validTo);
    }

    #[Test]
    public function itReturnsNullWhenPromotionNotFound(): void
    {
        $promotionId = PromotionId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($promotionId, $this->tenantId)
            ->willReturn(null);

        $query = new GetPromotionByIdQuery($promotionId, $this->tenantId);

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itDelegatesToRepositoryWithCorrectArguments(): void
    {
        $promotionId = PromotionId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (PromotionId $id) => $id->equals($promotionId)),
                self::callback(fn (TenantId $tid) => $tid->equals($this->tenantId))
            )
            ->willReturn(null);

        ($this->handler)(new GetPromotionByIdQuery($promotionId, $this->tenantId));
    }

    #[Test]
    public function itReturnsDtoWithActiveStatusWhenPromotionIsActive(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildPromotion($promotionId, 'Active Promo');
        $promotion->activate();

        $this->repository->method('findById')->willReturn($promotion);

        $result = ($this->handler)(new GetPromotionByIdQuery($promotionId, $this->tenantId));

        self::assertNotNull($result);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itReturnsDtoWithCouponCodeForCouponPromotion(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Coupon Promo',
            type: PromotionType::fromString('coupon'),
            discount: Discount::fromTypeAndValue('percentage', 20.0),
            priority: 50,
            couponCode: \App\Pricing\Domain\ValueObject\CouponCode::fromString('SAVE20'),
        );

        $this->repository->method('findById')->willReturn($promotion);

        $result = ($this->handler)(new GetPromotionByIdQuery($promotionId, $this->tenantId));

        self::assertNotNull($result);
        self::assertSame('SAVE20', $result->couponCode);
        self::assertSame('coupon', $result->type);
    }

    #[Test]
    public function itReturnsDtoWithValidityDatesWhenSet(): void
    {
        $promotionId = PromotionId::generate();
        $validFrom = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $validTo = new \DateTimeImmutable('2026-12-31T23:59:59+00:00');
        $promotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Seasonal Promo',
            type: PromotionType::fromString('catalog_rule'),
            discount: Discount::fromTypeAndValue('fixed_amount', 5.0),
            priority: 200,
            validFrom: $validFrom,
            validTo: $validTo,
        );

        $this->repository->method('findById')->willReturn($promotion);

        $result = ($this->handler)(new GetPromotionByIdQuery($promotionId, $this->tenantId));

        self::assertNotNull($result);
        self::assertNotNull($result->validFrom);
        self::assertNotNull($result->validTo);
    }

    #[Test]
    public function itReturnsDtoWithConditionsWhenPresent(): void
    {
        $promotionId = PromotionId::generate();
        $conditions = ['min_cart_value' => 100, 'product_ids' => ['prod-1', 'prod-2']];
        $promotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Conditional Promo',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: 100,
            conditions: $conditions,
        );

        $this->repository->method('findById')->willReturn($promotion);

        $result = ($this->handler)(new GetPromotionByIdQuery($promotionId, $this->tenantId));

        self::assertNotNull($result);
        self::assertSame($conditions, $result->conditions);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildPromotion(PromotionId $id, string $name): Promotion
    {
        return Promotion::create(
            id: $id,
            tenantId: $this->tenantId,
            name: $name,
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 15.0),
            priority: 100,
        );
    }
}
