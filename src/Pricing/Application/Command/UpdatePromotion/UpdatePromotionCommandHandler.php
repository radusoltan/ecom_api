<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\UpdatePromotion;

use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\Discount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePromotionCommandHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    public function __invoke(UpdatePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId, $command->tenantId);

        if ($promotion === null) {
            throw new \DomainException(
                sprintf('Promotion with ID "%s" not found', $command->promotionId->toString())
            );
        }

        $promotion->update(
            name: $command->name,
            discount: Discount::fromTypeAndValue($command->discountType, $command->discountValue),
            priority: $command->priority,
            conditions: $command->conditions,
            validFrom: $command->validFrom !== null ? new \DateTimeImmutable($command->validFrom) : null,
            validTo: $command->validTo !== null ? new \DateTimeImmutable($command->validTo) : null
        );

        $this->promotionRepository->save($promotion);
    }
}
