<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Payment\Application\Query\GetAllPayments;
use App\Payment\Application\Query\GetPaymentsByOrder;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PaymentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    /**
     * @return PaymentEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID is required');

        // Check if filtering by order_id
        $orderId = $context['filters']['orderId'] ?? null;

        if (null !== $orderId) {
            $query = new GetPaymentsByOrder(
                orderId: $orderId,
                tenantId: TenantId::fromString($tenantId)
            );
        } else {
            $query = new GetAllPayments(
                tenantId: TenantId::fromString($tenantId)
            );
        }

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $dtos = $handledStamp->getResult();

        // Convert DTOs to entities for API Platform
        return array_map(function ($dto) {
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
        }, $dtos);
    }
}
