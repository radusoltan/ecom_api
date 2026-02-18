<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CapturePayment;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform State Processor for capturing authorized payments.
 *
 * This is the third step in the payment flow:
 * 1. Create payment intent - reserves funds at gateway
 * 2. Confirm payment intent - completes authorization
 * 3. Capture payment (this processor) - transfers funds to merchant
 *
 * Supports partial capture - if amountInCents is provided and less than
 * authorized amount, only that amount will be captured.
 *
 * Typically called when order is shipped or ready to fulfill.
 *
 * Delegates all business logic to application layer commands.
 *
 * @implements ProcessorInterface<PaymentEntity, PaymentEntity>
 */
final readonly class CapturePaymentProcessor implements ProcessorInterface
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

        // Optional: capture partial amount (from request body)
        $amountInCents = $context['amount_in_cents'] ?? null;

        $command = new CapturePayment(
            id: PaymentId::fromString($paymentId),
            amountInCents: $amountInCents
        );

        $this->commandBus->dispatch($command);

        // Return entity (status will be updated by the handler)
        return $data;
    }
}
