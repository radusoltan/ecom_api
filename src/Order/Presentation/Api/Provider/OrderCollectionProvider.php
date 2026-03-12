<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Order\Application\Query\GetOrdersByCustomerQuery;
use App\Order\Infrastructure\Persistence\Doctrine\ReadModel\OrderListingReadRepository;
use App\Order\Presentation\Api\Resource\OrderResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class OrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private OrderListingReadRepository $readRepository,
        private RequestStack $requestStack,
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

        $request = $this->requestStack->getCurrentRequest();
        $status = $context['filters']['status'] ?? $request?->query->get('status');
        $sort = $request?->query->get('sort', 'newest');
        $page = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $itemsPerPage = max(1, min(1000, (int) ($request?->query->get('itemsPerPage', 1000) ?? 1000)));

        $orders = $this->readRepository->findForAdmin(
            tenantId: $tenantId,
            page: $page,
            itemsPerPage: $itemsPerPage,
            status: is_string($status) && '' !== $status ? $status : null,
            sort: is_string($sort) ? $sort : 'newest',
        )['orders'];

        return array_map(function ($orderDTO) {
            $resource = new OrderResource();
            $resource->id = $orderDTO->id;
            $resource->tenantId = $orderDTO->tenantId;
            $resource->customerEmail = $orderDTO->customerEmail;
            $resource->status = $orderDTO->status;
            $resource->lineCount = $orderDTO->lineCount;
            $resource->totalAmount = $orderDTO->totalAmount;
            $resource->totalCurrency = $orderDTO->totalCurrency;
            $resource->discountAmount = $orderDTO->discountAmount;
            $resource->couponCode = $orderDTO->couponCode;
            $resource->createdAt = $orderDTO->createdAt;
            $resource->updatedAt = $orderDTO->updatedAt;

            return $resource;
        }, $orders);
    }
}
