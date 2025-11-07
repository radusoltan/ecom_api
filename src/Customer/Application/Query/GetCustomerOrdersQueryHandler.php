<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerOrdersQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(GetCustomerOrdersQuery $query): array
    {
        // Find customer to get email
        $customer = $this->customerRepository->findById($query->customerId(), $query->tenantId());

        if (null === $customer) {
            return [];
        }

        // Find orders by customer email
        $orders = $this->orderRepository->findByCustomerEmail(
            $customer->email()->toString(),
            $query->tenantId()
        );

        return array_map(
            function ($order) {
                return [
                    'id' => $order->id()->toString(),
                    'status' => $order->status()->value(),
                    'total' => $order->total()->toArray(),
                    'createdAt' => $order->createdAt()->format('c'),
                ];
            },
            $orders
        );
    }
}
