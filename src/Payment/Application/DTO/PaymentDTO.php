<?php

declare(strict_types=1);

namespace App\Payment\Application\DTO;

use App\Payment\Domain\Model\Payment;

final readonly class PaymentDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $orderId,
        public int $amountInCents,
        public string $currency,
        public string $method,
        public string $gateway,
        public string $status,
        public ?string $gatewayTransactionId,
        public ?string $errorMessage,
        public int $refundedAmountInCents,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomainModel(Payment $payment): self
    {
        return new self(
            id: $payment->id()->toString(),
            tenantId: $payment->tenantId()->toString(),
            orderId: $payment->orderId(),
            amountInCents: $payment->amount()->getAmount(),
            currency: $payment->currency(),
            method: $payment->method()->value(),
            gateway: $payment->gateway()->value(),
            status: $payment->status()->value(),
            gatewayTransactionId: $payment->gatewayTransactionId(),
            errorMessage: $payment->errorMessage(),
            refundedAmountInCents: $payment->refundedAmount()->getAmount(),
            createdAt: $payment->createdAt(),
            updatedAt: $payment->updatedAt()
        );
    }
}
