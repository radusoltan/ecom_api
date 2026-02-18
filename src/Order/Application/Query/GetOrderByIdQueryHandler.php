<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

use App\Order\Application\DTO\OrderDTO;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOrderByIdQueryHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GetOrderByIdQuery $query): ?OrderDTO
    {
        $this->profiler->start('order.get_by_id');

        try {
            $order = $this->orderRepository->findByIdAndTenant(
                OrderId::fromString($query->orderId),
                TenantId::fromString($query->tenantId)
            );

            $result = $order ? OrderDTO::fromDomain($order) : null;

            $metrics = $this->profiler->stop('order.get_by_id');

            // Log if slow (> 100ms for read operations)
            if ($metrics['duration_ms'] > 100) {
                $this->logger->warning('Slow order query detected', [
                    'order_id' => $query->orderId,
                    'metrics' => $metrics,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profiler->stop('order.get_by_id');

            throw $e;
        }
    }
}
