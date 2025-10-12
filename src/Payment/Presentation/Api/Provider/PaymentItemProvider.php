<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PaymentItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PaymentEntity
    {
        $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID is required');

        $query = new GetPaymentById(
            id: PaymentId::fromString($uriVariables['id']),
            tenantId: TenantId::fromString($tenantId)
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $dto = $handledStamp->getResult();

        if ($dto === null) {
            return null;
        }

        // Convert DTO to entity for API Platform
        $entity = new PaymentEntity();
        $entity->setId($dto->id);
        $entity->setTenantId($dto->tenantId);
        $entity->setOrderId($dto->orderId);
        $entity->setAmountInCents($dto->amountInCents);
        $entity->setCurrency($dto->currency);
        $entity->setMethod($dto->method);
        $entity->setGateway($dto->gateway);
        $entity->setStatus($dto->status);
        $entity->setGatewayTransactionId($dto->gatewayTransactionId);
        $entity->setErrorMessage($dto->errorMessage);
        $entity->setRefundedAmountInCents($dto->refundedAmountInCents);
        $entity->setCreatedAt($dto->createdAt);
        $entity->setUpdatedAt($dto->updatedAt);

        return $entity;
    }
}
