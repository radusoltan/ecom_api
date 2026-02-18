<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CreatePayment;
use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class CreatePaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    /**
     * @param PaymentEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentEntity
    {
        // Generate ID if not provided (ID is optional in POST requests)
        $paymentId = PaymentId::generate();

        // Get tenant ID from context (set by TenantContextProcessor)
        $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID is required');

        $command = new CreatePayment(
            id: $paymentId,
            tenantId: TenantId::fromString($tenantId),
            orderId: $data->getOrderId(),
            amountInCents: $data->getAmountInCents(),
            currency: $data->getCurrency(),
            method: PaymentMethod::fromString($data->getMethod()),
            gateway: PaymentGateway::fromString($data->getGateway())
        );

        $this->commandBus->dispatch($command);

        // Retrieve the created payment with all fields populated
        $query = new GetPaymentById(id: $paymentId, tenantId: TenantId::fromString($tenantId));
        $envelope = $this->queryBus->dispatch($query);
        $paymentDTO = $envelope->last(HandledStamp::class)?->getResult();

        if (!$paymentDTO) {
            throw new \RuntimeException('Payment was not created');
        }

        // Convert DTO to Entity for API response
        return PaymentEntity::fromDTO($paymentDTO);
    }
}
