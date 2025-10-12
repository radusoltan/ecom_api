<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Order\Domain\Model\OrderId;
use App\Order\Presentation\Api\Resource\OrderResource;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PlaceOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResource
    {
        if (!$data instanceof OrderResource) {
            throw new InvalidArgumentException('Expected OrderResource');
        }

        if (!$data->customerEmail || !$data->lines || !$data->shippingAddress || !$data->billingAddress) {
            throw new InvalidArgumentException('Customer email, lines, shipping address, and billing address are required');
        }

        $orderId = OrderId::generate()->toString();
        $tenantId = $data->tenantId ?? throw new InvalidArgumentException('Tenant ID is required');

        $command = new PlaceOrderCommand(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: $data->customerEmail,
            lines: $data->lines,
            shippingAddress: $data->shippingAddress,
            billingAddress: $data->billingAddress,
            couponCode: $data->couponCode ?? null,
            promotionContext: $data->promotionContext ?? []
        );

        $this->commandBus->dispatch($command);

        // Retrieve the created order
        $envelope = $this->queryBus->dispatch(new GetOrderByIdQuery($orderId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new RuntimeException('No handler found for query');
        }

        $orderDTO = $handledStamp->getResult();

        if ($orderDTO === null) {
            throw new RuntimeException('Order not found after creation');
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
        $resource->appliedPromotions = $orderDTO->appliedPromotions ?? [];
        $resource->discountAmount = $orderDTO->discountAmount ?? null;
        $resource->discountCurrency = $orderDTO->discountCurrency ?? null;
        $resource->couponCode = $orderDTO->couponCode ?? null;
        $resource->createdAt = $orderDTO->createdAt;
        $resource->updatedAt = $orderDTO->updatedAt;

        return $resource;
    }
}
