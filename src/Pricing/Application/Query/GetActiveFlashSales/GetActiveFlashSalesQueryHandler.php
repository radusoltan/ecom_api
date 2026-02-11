<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActiveFlashSales;

use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetActiveFlashSalesQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository
    ) {
    }

    /**
     * @return array<\App\Pricing\Domain\Model\FlashSale>
     */
    public function __invoke(GetActiveFlashSalesQuery $query): array
    {
        return $this->flashSaleRepository->findActiveByTenant($query->tenantId);
    }
}
