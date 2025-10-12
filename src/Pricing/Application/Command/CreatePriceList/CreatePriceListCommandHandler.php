<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CreatePriceList;

use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Infrastructure\Performance\PerformanceProfiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler: Create a new PriceList
 */
#[AsMessageHandler]
final readonly class CreatePriceListCommandHandler
{
    public function __construct(
        private PriceListRepositoryInterface $priceListRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CreatePriceListCommand $command): void
    {
        $this->profiler->start('price_list.create');

        try {
            $priceList = PriceList::create(
                $command->priceListId,
                $command->tenantId,
                $command->name,
                $command->priority,
                $command->validFrom,
                $command->validTo
            );

            $this->priceListRepository->save($priceList);

            $metrics = $this->profiler->stop('price_list.create');

            // Log if slow (> 200ms for write operations)
            if ($metrics['duration_ms'] > 200) {
                $this->logger->warning('Slow price list creation detected', [
                    'price_list_name' => $command->name,
                    'metrics' => $metrics,
                ]);
            }
        } catch (\Throwable $e) {
            $this->profiler->stop('price_list.create');
            throw $e;
        }
    }
}
