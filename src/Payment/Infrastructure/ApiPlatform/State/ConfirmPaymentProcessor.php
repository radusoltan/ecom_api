<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\ConfirmPayment\ConfirmPaymentCommand;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform State Processor for confirming payment intents.
 *
 * This is the second step in the payment intent flow:
 * 1. Create payment intent - reserves funds at gateway
 * 2. Confirm payment intent (this processor) - completes authorization
 * 3. Capture payment - transfers funds (on order shipment)
 *
 * Confirmation may require additional customer action (3D Secure, SCA).
 * The payment method ID comes from the frontend payment form (Stripe Elements, etc.)
 *
 * Delegates all business logic to application layer commands.
 *
 * @implements ProcessorInterface<PaymentEntity, PaymentEntity>
 */
final readonly class ConfirmPaymentProcessor implements ProcessorInterface
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

        // Payment method ID should come from request body
        $paymentMethodId = $context['payment_method_id'] ?? $data->getGatewayTransactionId()
            ?? throw new \RuntimeException('Payment method ID is required');

        $command = new ConfirmPaymentCommand(
            paymentId: PaymentId::fromString($paymentId),
            paymentMethodId: $paymentMethodId
        );

        $this->commandBus->dispatch($command);

        // Return entity (status will be updated by the handler)
        return $data;
    }
}
