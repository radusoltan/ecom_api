<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\ActivatePromotion\ActivatePromotionCommand;
use App\Pricing\Application\Command\ActivatePromotion\ActivatePromotionCommandHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivatePromotionCommandHandler::class)]
final class ActivatePromotionCommandHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private ActivatePromotionCommandHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new ActivatePromotionCommandHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itActivatesPromotionAndSaves(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildInactivePromotion($promotionId);

        $this->repository
            ->method('findById')
            ->with($promotionId, $this->tenantId)
            ->willReturn($promotion);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Promotion $p): bool {
                self::assertTrue($p->isActive());

                return true;
            }));

        $command = new ActivatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
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

        ($this->handler)(new ActivatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
        ));
    }

    #[Test]
    public function itThrowsDomainExceptionWhenPromotionAlreadyActive(): void
    {
        $promotionId = PromotionId::generate();
        $promotion = $this->buildInactivePromotion($promotionId);
        $promotion->activate();

        $this->repository->method('findById')->willReturn($promotion);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already active/');

        ($this->handler)(new ActivatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $this->tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildInactivePromotion(PromotionId $id): Promotion
    {
        return Promotion::create(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Test Promotion',
            type: PromotionType::fromString('cart_rule'),
            discount: Discount::fromTypeAndValue('percentage', 15.0),
            priority: 100,
        );
    }
}
