<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Repository;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(Payment $payment): void
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $existing = $repository->findOneBy([
            'id' => $payment->id()->toString(),
            'tenantId' => $payment->tenantId()->toString(),
        ]);

        if ($existing instanceof PaymentEntity) {
            $existing->updateFromDomainModel($payment);
        } else {
            $entity = PaymentEntity::fromDomainModel($payment);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($payment->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(PaymentId $id, TenantId $tenantId): ?Payment
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        if (!$entity instanceof PaymentEntity) {
            return null;
        }

        // Refresh entity from database to avoid stale data from identity map
        $this->entityManager->refresh($entity);

        return $entity->toDomainModel();
    }

    public function findByOrderId(string $orderId, TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entities = $repository->findBy(
            [
                'orderId' => $orderId,
                'tenantId' => $tenantId->toString(),
            ],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn(PaymentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByGatewayTransactionId(string $gatewayTransactionId, string $tenantId): ?Payment
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy([
            'gatewayTransactionId' => $gatewayTransactionId,
            'tenantId' => $tenantId,
        ]);

        if (!$entity instanceof PaymentEntity) {
            return null;
        }

        // Refresh entity from database to avoid stale data from identity map
        $this->entityManager->refresh($entity);

        return $entity->toDomainModel();
    }

    public function findAll(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn(PaymentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(Payment $payment): void
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy([
            'id' => $payment->id()->toString(),
            'tenantId' => $payment->tenantId()->toString(),
        ]);

        if ($entity instanceof PaymentEntity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
