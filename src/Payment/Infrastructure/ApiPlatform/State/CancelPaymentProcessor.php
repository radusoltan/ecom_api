<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CancelPayment;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform State Processor for canceling/voiding authorized payments.
 *
 * Cancellation releases reserved funds back to the customer.
 * Can only cancel payments that have NOT been captured yet.
 *
 * Use Cases:
 * - Order cancelled before shipment
 * - Customer requested cancellation
 * - Inventory unavailable
 * - Fraud prevention
 *
 * Delegates all business logic to application layer commands.
 *
 * @implements ProcessorInterface<PaymentEntity, PaymentEntity>
 */
final readonly class CancelPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @param PaymentEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentEntity
    {
        $paymentId = $uriVariables['id'] ?? throw new \RuntimeException('Payment ID is required');

        // Reason should come from request body
        $reason = $context['reason'] ?? $data->getErrorMessage() ?? 'Cancelled by request';

        $command = new CancelPayment(
            id: PaymentId::fromString($paymentId),
            reason: $reason
        );

        $this->commandBus->dispatch($command);

        // Return entity (status will be updated by the handler)
        return $data;
    }
}
