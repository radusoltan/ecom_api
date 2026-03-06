<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\DeactivatePromotion\DeactivatePromotionCommand;
use App\Pricing\Application\Command\DeactivatePromotion\DeactivatePromotionCommandHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeactivatePromotionCommandHandler::class)]
final class DeactivatePromotionCommandHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private DeactivatePromotionCommandHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new DeactivatePromotionCommandHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeactivatesPromotionAndSaves(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildActivePromotion($promotionId);

        $this->repository
            ->method('findById')
            ->with($promotionId, $this->tenantId)
            ->willReturn($promotion);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Promotion $p): bool {
                self::assertFalse($p->isActive());

                return true;
            }));

        ($this->handler)(new DeactivatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Failure paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenPromotionNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not found/');

        ($this->handler)(new DeactivatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: $this->tenantId,
        ));
    }

    #[Test]
    public function itThrowsDomainExceptionWhenPromotionAlreadyInactive(): void
    {
        $promotionId = PromotionId::generate();
        // Build inactive promotion (not activated)
        $promotion = Promotion::create(
            id: $promotionId,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 15.0),
            priority: 100,
        );

        $this->repository->method('findById')->willReturn($promotion);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already inactive/');

        ($this->handler)(new DeactivatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildActivePromotion(PromotionId $id): Promotion
    {
        $promotion = Promotion::create(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 15.0),
            priority: 100,
        );
        $promotion->activate();

        return $promotion;
    }
}
