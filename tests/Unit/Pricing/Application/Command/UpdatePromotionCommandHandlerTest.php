<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\UpdatePromotion\UpdatePromotionCommand;
use App\Pricing\Application\Command\UpdatePromotion\UpdatePromotionCommandHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePromotionCommandHandler::class)]
final class UpdatePromotionCommandHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private UpdatePromotionCommandHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new UpdatePromotionCommandHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesPromotionAndSaves(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildPromotion($promotionId);

        $this->repository
            ->method('findById')
            ->with($promotionId, $this->tenantId)
            ->willReturn($promotion);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Promotion $p): bool {
                self::assertSame('Updated Name', $p->name());
                self::assertSame(200, $p->priority());

                return true;
            }));

        $command = new UpdatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
            name: 'Updated Name',
            discountType: 'percentage',
            discountValue: 20.0,
            priority: 200,
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itUpdatesPromotionWithValidityDates(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildPromotion($promotionId);

        $this->repository->method('findById')->willReturn($promotion);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Promotion $p): bool {
                self::assertNotNull($p->validFrom());
                self::assertNotNull($p->validTo());

                return true;
            }));

        $command = new UpdatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
            name: 'Seasonal Promo',
            discountType: 'fixed_amount',
            discountValue: 10.0,
            priority: 100,
            conditions: [],
            validFrom: '2026-01-01',
            validTo: '2026-12-31',
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Failure paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenPromotionNotFound(): void
    {
        $promotionId = PromotionId::generate();

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $command = new UpdatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
            name: 'Updated Name',
            discountType: 'percentage',
            discountValue: 10.0,
            priority: 100,
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildPromotion(PromotionId $promotionId): Promotion
    {
        return Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Original Name',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: 100,
        );
    }
}
