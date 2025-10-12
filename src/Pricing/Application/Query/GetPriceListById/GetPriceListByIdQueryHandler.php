<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceListById;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Infrastructure\Performance\PerformanceProfiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPriceListByIdQueryHandler
{
    public function __construct(
        private PriceListRepositoryInterface $priceListRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GetPriceListByIdQuery $query): ?PriceListDTO
    {
        $this->profiler->start('price_list.get_by_id');

        try {
            $priceList = $this->priceListRepository->findById($query->priceListId);

            if ($priceList === null) {
                $this->profiler->stop('price_list.get_by_id');
                return null;
            }

            $result = PriceListDTO::fromDomainModel($priceList);

            $metrics = $this->profiler->stop('price_list.get_by_id');

            // Log if slow (> 100ms for read operations)
            if ($metrics['duration_ms'] > 100) {
                $this->logger->warning('Slow price list query detected', [
                    'price_list_id' => $query->priceListId->toString(),
                    'metrics' => $metrics,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profiler->stop('price_list.get_by_id');
            throw $e;
        }
    }
}
