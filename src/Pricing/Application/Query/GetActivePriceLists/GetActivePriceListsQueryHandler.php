<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActivePriceLists;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetActivePriceListsQueryHandler
{
    public function __construct(
        private PriceListRepositoryInterface $priceListRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<PriceListDTO>
     */
    public function __invoke(GetActivePriceListsQuery $query): array
    {
        $this->profiler->start('price_list.get_active');

        try {
            $priceLists = $this->priceListRepository->findValidForTenant($query->tenantId);

            $result = array_map(
                fn ($priceList) => PriceListDTO::fromDomainModel($priceList),
                $priceLists
            );

            $metrics = $this->profiler->stop('price_list.get_active');

            // Log if slow (> 100ms for read operations)
            if ($metrics['duration_ms'] > 100) {
                $this->logger->warning('Slow active price lists query detected', [
                    'tenant_id' => $query->tenantId->toString(),
                    'price_list_count' => count($result),
                    'metrics' => $metrics,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profiler->stop('price_list.get_active');

            throw $e;
        }
    }
}
