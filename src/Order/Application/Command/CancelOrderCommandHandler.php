<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orderRepository->findByIdAndTenant(
            OrderId::fromString($command->orderId),
            TenantId::fromString($command->tenantId)
        );

        if ($order === null) {
            throw new RuntimeException(sprintf('Order with ID "%s" not found', $command->orderId));
        }

        $order->cancel();

        $this->orderRepository->save($order);
    }
}
