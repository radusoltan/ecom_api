<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

use App\Order\Application\DTO\OrderDTO;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOrdersByCustomerQueryHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    public function __invoke(GetOrdersByCustomerQuery $query): array
    {
        $orders = $this->orderRepository->findByCustomerEmail(
            $query->customerEmail,
            TenantId::fromString($query->tenantId)
        );

        return array_map(
            fn ($order) => OrderDTO::fromDomain($order),
            $orders
        );
    }
}
