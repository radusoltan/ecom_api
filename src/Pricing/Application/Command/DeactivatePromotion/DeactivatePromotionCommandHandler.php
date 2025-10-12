<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\DeactivatePromotion;

use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeactivatePromotionCommandHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    public function __invoke(DeactivatePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId, $command->tenantId);

        if ($promotion === null) {
            throw new \DomainException(
                sprintf('Promotion with ID "%s" not found', $command->promotionId->toString())
            );
        }

        $promotion->deactivate();

        $this->promotionRepository->save($promotion);
    }
}
