<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPayment;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetPaymentQuery.
 *
 * Retrieves a payment aggregate by its identifier.
 * Returns the domain model directly (not DTO) for maximum flexibility.
 */
#[AsMessageHandler]
final readonly class GetPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository
    ) {
    }

    public function __invoke(GetPaymentQuery $query): ?Payment
    {
        return $this->paymentRepository->findById($query->paymentId);
    }
}
