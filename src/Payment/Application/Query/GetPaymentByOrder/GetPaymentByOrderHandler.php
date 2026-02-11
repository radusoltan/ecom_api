<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentByOrder;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetPaymentByOrderQuery.
 *
 * Finds the first payment for an order.
 * Returns null if no payment exists for the order.
 */
#[AsMessageHandler]
final readonly class GetPaymentByOrderHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository
    ) {
    }

    public function __invoke(GetPaymentByOrderQuery $query): ?Payment
    {
        return $this->paymentRepository->findByOrderId($query->orderId);
    }
}
