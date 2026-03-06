<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Application\Command\CreatePromotion\CreatePromotionCommandHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePromotionCommandHandler::class)]
final class CreatePromotionCommandHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $promotionRepository;
    private CreatePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepositoryInterface::class);
        $this->handler = new CreatePromotionCommandHandler($this->promotionRepository);
    }

    #[Test]
    public function itCreatesCartRulePromotionAndSaves(): void
    {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: 'Summer Sale',
            type: 'cart_rule',
            discountType: 'percentage',
            discountValue: 10.0,
            priority: 1,
            couponCode: null,
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        $this->promotionRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Promotion::class));

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesCouponPromotionWithCode(): void
    {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: 'VIP Discount',
            type: 'coupon',
            discountType: 'fixed_amount',
            discountValue: 25.0,
            priority: 2,
            couponCode: 'VIP2025',
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        $this->promotionRepository->expects(self::once())->method('save');

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesPromotionWithValidityDates(): void
    {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: 'Holiday Sale',
            type: 'catalog_rule',
            discountType: 'percentage',
            discountValue: 15.0,
            priority: 3,
            couponCode: null,
            conditions: [],
            validFrom: '2025-12-20 00:00:00',
            validTo: '2025-12-31 23:59:59',
        );

        $this->promotionRepository->expects(self::once())->method('save');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsBadRequestOnInvalidType(): void
    {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: 'Bad Promo',
            type: 'invalid_type',
            discountType: 'percentage',
            discountValue: 10.0,
            priority: 1,
            couponCode: null,
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        $this->promotionRepository->expects(self::never())->method('save');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsBadRequestOnInvalidDiscountValue(): void
    {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: 'Bad Discount',
            type: 'cart_rule',
            discountType: 'percentage',
            discountValue: 150.0,
            priority: 1,
            couponCode: null,
            conditions: [],
            validFrom: null,
            validTo: null,
        );

        $this->promotionRepository->expects(self::never())->method('save');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);

        ($this->handler)($command);
    }
}
