<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ActivatePriceList;

use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivatePriceListCommandHandler
{
    public function __construct(
        private PriceListRepositoryInterface $priceListRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ActivatePriceListCommand $command): void
    {
        $this->profiler->start('price_list.activate');

        try {
            $priceList = $this->priceListRepository->findById($command->priceListId);

            if (null === $priceList) {
                throw new \InvalidArgumentException(sprintf('PriceList with ID %s not found', $command->priceListId->toString()));
            }

            $priceList->activate();

            $this->priceListRepository->save($priceList);

            $metrics = $this->profiler->stop('price_list.activate');

            // Log if slow (> 200ms for write operations)
            if ($metrics['duration_ms'] > 200) {
                $this->logger->warning('Slow price list activation detected', [
                    'price_list_id' => $command->priceListId->toString(),
                    'metrics' => $metrics,
                ]);
            }
        } catch (\Throwable $e) {
            $this->profiler->stop('price_list.activate');

            throw $e;
        }
    }
}
