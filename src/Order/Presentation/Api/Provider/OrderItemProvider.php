<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Order\Presentation\Api\Resource\OrderResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class OrderItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrderResource
    {
        $orderId = $uriVariables['id'] ?? throw new \InvalidArgumentException('Order ID is required');

        // Get tenantId from context (should be injected by security layer)
        $tenantId = $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required');

        $envelope = $this->queryBus->dispatch(new GetOrderByIdQuery($orderId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $orderDTO = $handledStamp->getResult();

        if (null === $orderDTO) {
            return null;
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
