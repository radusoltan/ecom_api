<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActivePromotions;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetActivePromotionsQueryHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    /**
     * @return PromotionDTO[]
     */
    public function __invoke(GetActivePromotionsQuery $query): array
    {
        $promotions = $this->promotionRepository->findActivePromotions($query->tenantId);

        return array_map(
            static fn ($promotion) => PromotionDTO::fromDomain($promotion),
            $promotions
        );
    }
}
