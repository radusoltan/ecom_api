<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\CancelOrderCommand;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Order\Presentation\Api\Resource\OrderResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class CancelOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResource
    {
        if (!$data instanceof OrderResource) {
            throw new \InvalidArgumentException('Expected OrderResource');
        }

        $orderId = $uriVariables['id'] ?? throw new \InvalidArgumentException('Order ID is required');
        $tenantId = $data->tenantId ?? throw new \InvalidArgumentException('Tenant ID is required');

        $command = new CancelOrderCommand(
            orderId: $orderId,
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);

        // Retrieve cancelled order
        $envelope = $this->queryBus->dispatch(new GetOrderByIdQuery($orderId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $orderDTO = $handledStamp->getResult();

        if (null === $orderDTO) {
            throw new \RuntimeException('Order not found after cancellation');
        }

        $resource = new OrderResource();
        $resource->id = $orderDTO->id;
        $resource->tenantId = $orderDTO->tenantId;
        $resource->customerEmail = $orderDTO->customerEmail;
        $resource->status = $orderDTO->status;
        $resource->lines = $orderDTO->lines;
        $resource->shippingAddress = $orderDTO->shippingAddress;
        $resource->billingAddress = $orderDTO->billingAddress;
        $resource->totalAmount = $orderDTO->totalAmount;
        $resource->totalCurrency = $orderDTO->totalCurrency;
        $resource->createdAt = $orderDTO->createdAt;
        $resource->updatedAt = $orderDTO->updatedAt;

        return $resource;
    }
}
