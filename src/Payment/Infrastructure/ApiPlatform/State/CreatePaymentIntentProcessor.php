<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CreatePaymentIntent\CreatePaymentIntentCommand;
use App\Payment\Application\Command\CreatePaymentIntent\CreatePaymentIntentResult;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * API Platform State Processor for creating payment intents.
 *
 * This is the first step in the payment intent flow:
 * 1. Create payment intent (this processor) - reserves funds at gateway
 * 2. Confirm payment intent - completes authorization (may require 3DS)
 * 3. Capture payment - transfers funds (on order shipment)
 *
 * Delegates all business logic to application layer commands.
 *
 * @implements ProcessorInterface<PaymentEntity, PaymentEntity>
 */
final readonly class CreatePaymentIntentProcessor implements ProcessorInterface
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
        // Extract tenant from context (X-Tenant-ID header)
        $tenantIdString = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID is required');
        $tenantId = TenantId::fromString($tenantIdString);

        // Generate idempotency key from order ID
        $idempotencyKey = $this->generateIdempotencyKey($data->getOrderId());

        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: $data->getOrderId(),
            amountInCents: $data->getAmountInCents(),
            currency: $data->getCurrency(),
            paymentMethod: $data->getMethod(),
            idempotencyKey: $idempotencyKey
        );

        $envelope = $this->commandBus->dispatch($command);
        $result = $envelope->last(HandledStamp::class)?->getResult();

        if (!$result instanceof CreatePaymentIntentResult) {
            throw new \RuntimeException('Invalid result from CreatePaymentIntent command');
        }

        if (!$result->isSuccessful()) {
            throw new \RuntimeException($result->errorMessage ?? 'Payment intent creation failed');
        }

        // Update entity with gateway details
        $data->setId($result->paymentId->toString());
        $data->setTenantId($tenantId->toString());
        $data->setGatewayTransactionId($result->gatewayPaymentId);
        $data->setStatus('pending');

        return $data;
    }

    private function generateIdempotencyKey(string $orderId): string
    {
        return sprintf('order_%s_payment_%s', $orderId, bin2hex(random_bytes(8)));
    }
}
