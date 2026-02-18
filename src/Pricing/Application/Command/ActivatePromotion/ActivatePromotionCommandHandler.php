<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ActivatePromotion;

use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivatePromotionCommandHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    public function __invoke(ActivatePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId, $command->tenantId);

        if (null === $promotion) {
            throw new \DomainException(sprintf('Promotion with ID "%s" not found', $command->promotionId->toString()));
        }

        $promotion->activate();

        $this->promotionRepository->save($promotion);
    }
}
