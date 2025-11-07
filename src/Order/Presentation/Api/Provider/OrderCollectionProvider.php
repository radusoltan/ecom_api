<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Order\Application\Query\GetAllOrdersQuery;
use App\Order\Application\Query\GetOrdersByCustomerQuery;
use App\Order\Presentation\Api\Resource\OrderResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class OrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get tenantId from context (should be injected by security layer)
        $tenantId = $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required');

        // Check if filtering by customer email
        $customerEmail = $context['filters']['customerEmail'] ?? null;

        if (null !== $customerEmail) {
            $query = new GetOrdersByCustomerQuery($customerEmail, $tenantId);
        } else {
            $query = new GetAllOrdersQuery($tenantId);
        }

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $orderDTOs = $handledStamp->getResult();

        if (!is_array($orderDTOs)) {
            return [];
        }

        return array_map(function ($orderDTO) {
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
        }, $orderDTOs);
    }
}
