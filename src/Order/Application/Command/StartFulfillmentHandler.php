<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Start Fulfillment Command Handler
 *
 * Creates a new fulfillment record and initiates the warehouse fulfillment process.
 */
#[AsMessageHandler]
final readonly class StartFulfillmentHandler
{
    public function __construct(
        private FulfillmentRepositoryInterface $fulfillmentRepository,
        private OrderRepositoryInterface $orderRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartFulfillment $command): void
    {
        // Verify order exists
        $order = $this->orderRepository->findById($command->orderId);
        if ($order === null) {
            throw new \DomainException(sprintf('Order not found: %s', $command->orderId->toString()));
        }

        // Check if fulfillment already exists for this order
        $existingFulfillment = $this->fulfillmentRepository->findByOrderId($command->orderId);
        if ($existingFulfillment !== null) {
            throw new \DomainException(sprintf('Fulfillment already exists for order: %s', $command->orderId->toString()));
        }

        // Create and start fulfillment
        $fulfillment = Fulfillment::start(
            $command->fulfillmentId,
            $command->orderId,
            $command->warehouseId,
            $command->tenantId,
        );

        $this->fulfillmentRepository->save($fulfillment);

        $this->logger->info('Fulfillment started', [
            'fulfillment_id' => $command->fulfillmentId->toString(),
            'order_id' => $command->orderId->toString(),
            'warehouse_id' => $command->warehouseId->toString(),
            'tenant_id' => $command->tenantId->toString(),
        ]);
    }
}
