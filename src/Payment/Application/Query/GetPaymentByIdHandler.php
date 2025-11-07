<?php

declare(strict_types=1);

namespace App\Payment\Application\Query;

use App\Payment\Application\DTO\PaymentDTO;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPaymentByIdHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository
    ) {
    }

    public function __invoke(GetPaymentById $query): ?PaymentDTO
    {
        $payment = $this->paymentRepository->findById($query->id, $query->tenantId);

        if (null === $payment) {
            return null;
        }

        return PaymentDTO::fromDomainModel($payment);
    }
}
