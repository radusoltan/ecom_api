<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetUpcomingFlashSales;

use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUpcomingFlashSalesQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository
    ) {
    }

    /**
     * @return array<\App\Pricing\Domain\Model\FlashSale>
     */
    public function __invoke(GetUpcomingFlashSalesQuery $query): array
    {
        return $this->flashSaleRepository->findUpcoming($query->tenantId);
    }
}
