<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    public function __invoke(CreatePayment $command): void
    {
        $payment = Payment::create(
            id: $command->id,
            tenantId: $command->tenantId,
            orderId: $command->orderId,
            amount: $command->amount,
            method: $command->method,
            gateway: $command->gateway
        );

        $this->paymentRepository->save($payment);
    }
}
