<?php

declare(strict_types=1);

namespace App\Payment\Application\Query;

use App\Payment\Application\DTO\PaymentDTO;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllPaymentsHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    /**
     * @return PaymentDTO[]
     */
    public function __invoke(GetAllPayments $query): array
    {
        $payments = $this->paymentRepository->findAll($query->tenantId);

        return array_map(
            fn ($payment) => PaymentDTO::fromDomainModel($payment),
            $payments
        );
    }
}
