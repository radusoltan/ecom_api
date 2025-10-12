<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateOrderStatusCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    public function __invoke(UpdateOrderStatusCommand $command): void
    {
        $order = $this->orderRepository->findByIdAndTenant(
            OrderId::fromString($command->orderId),
            TenantId::fromString($command->tenantId)
        );

        if ($order === null) {
            throw new RuntimeException(sprintf('Order with ID "%s" not found', $command->orderId));
        }

        match ($command->newStatus) {
            OrderStatus::PROCESSING => $order->startProcessing(),
            OrderStatus::SHIPPED => $order->markAsShipped(),
            OrderStatus::DELIVERED => $order->markAsDelivered(),
            default => throw new RuntimeException(sprintf('Invalid order status: "%s"', $command->newStatus))
        };

        $this->orderRepository->save($order);
    }
}
