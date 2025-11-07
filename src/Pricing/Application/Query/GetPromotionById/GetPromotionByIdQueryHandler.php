<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPromotionById;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPromotionByIdQueryHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    public function __invoke(GetPromotionByIdQuery $query): ?PromotionDTO
    {
        $promotion = $this->promotionRepository->findById($query->promotionId, $query->tenantId);

        if (null === $promotion) {
            return null;
        }

        return PromotionDTO::fromDomain($promotion);
    }
}
