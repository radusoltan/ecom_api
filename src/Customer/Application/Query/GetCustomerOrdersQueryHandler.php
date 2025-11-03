<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerOrdersQueryHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(GetCustomerOrdersQuery $query): array
    {
        $orders = $this->orderRepository->findByCustomerId($query->customerId());

        return array_map(
            function ($order) {
                return [
                    'id' => $order->id()->toString(),
                    'orderNumber' => $order->orderNumber(),
                    'status' => $order->status()->value(),
                    'total' => [
                        'amount' => $order->totalAmount()->getAmount()->toFloat(),
                        'currency' => $order->totalAmount()->getCurrency()->getCurrencyCode(),
                    ],
                    'createdAt' => $order->createdAt()->format('c'),
                ];
            },
            $orders
        );
    }
}
