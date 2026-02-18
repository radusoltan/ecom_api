<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CreatePromotion;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePromotionCommandHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    public function __invoke(CreatePromotionCommand $command): void
    {
        try {
            $promotion = Promotion::create(
                id: $command->promotionId,
                tenantId: $command->tenantId,
                name: $command->name,
                type: PromotionType::fromString($command->type),
                discount: Discount::fromTypeAndValue($command->discountType, $command->discountValue),
                priority: $command->priority,
                couponCode: null !== $command->couponCode ? CouponCode::fromString($command->couponCode) : null,
                conditions: $command->conditions,
                validFrom: null !== $command->validFrom ? new \DateTimeImmutable($command->validFrom) : null,
                validTo: null !== $command->validTo ? new \DateTimeImmutable($command->validTo) : null
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($exception->getMessage(), $exception);
        }

        $this->promotionRepository->save($promotion);
    }
}
