<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\RefundPayment;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform State Processor for refunding captured payments.
 *
 * Refunds return money to the customer's original payment method.
 * Supports partial refunds - can refund less than the captured amount.
 *
 * Use Cases:
 * - Order returned by customer
 * - Product damaged/defective
 * - Service not delivered
 * - Customer requested refund
 * - Partial refund for damaged items
 *
 * Business Rules:
 * - Can only refund captured payments
 * - Refund amount cannot exceed (captured amount - already refunded amount)
 * - Supports multiple partial refunds up to total captured amount
 *
 * Delegates all business logic to application layer commands.
 *
 * @implements ProcessorInterface<PaymentEntity, PaymentEntity>
 */
final readonly class RefundPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    /**
     * @param PaymentEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentEntity
    {
        $paymentId = $uriVariables['id'] ?? throw new \RuntimeException('Payment ID is required');

        // Refund amount and reason should come from request body
        $refundAmountInCents = $context['refund_amount_in_cents'] ?? $data->getAmountInCents()
            ?? throw new \RuntimeException('Refund amount is required');

        $reason = $context['reason'] ?? 'Refund requested';

        $command = new RefundPayment(
            id: PaymentId::fromString($paymentId),
            refundAmountInCents: (int) $refundAmountInCents,
            reason: $reason
        );

        $this->commandBus->dispatch($command);

        // Return entity (refundedAmountInCents will be updated by the handler)
        return $data;
    }
}
