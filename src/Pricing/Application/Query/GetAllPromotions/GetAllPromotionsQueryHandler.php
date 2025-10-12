<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetAllPromotions;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllPromotionsQueryHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    /**
     * @return PromotionDTO[]
     */
    public function __invoke(GetAllPromotionsQuery $query): array
    {
        $promotions = $this->promotionRepository->findAll($query->tenantId);

        return array_map(
            static fn ($promotion) => PromotionDTO::fromDomain($promotion),
            $promotions
        );
    }
}
