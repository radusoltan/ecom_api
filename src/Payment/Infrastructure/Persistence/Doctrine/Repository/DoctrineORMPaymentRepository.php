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
        private EventDispatcherInterface $eventDispatcher,
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

    public function findById(PaymentId $id): ?Payment
    {
        $entity = $this->entityManager->find(PaymentEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByOrderId(string $orderId): ?Payment
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy(['orderId' => $orderId]);

        return $entity?->toDomainModel();
    }

    public function findByGatewayPaymentId(string $gatewayPaymentId): ?Payment
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy(['gatewayTransactionId' => $gatewayPaymentId]);

        return $entity?->toDomainModel();
    }

    public function findByIdempotencyKey(string $idempotencyKey, TenantId $tenantId): ?Payment
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entity = $repository->findOneBy([
            'idempotencyKey' => $idempotencyKey,
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    public function findAllByOrderId(string $orderId): array
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);
        $entities = $repository->findBy(
            ['orderId' => $orderId],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn (PaymentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findPaymentsForRetry(\DateTimeImmutable $before): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(PaymentEntity::class, 'p')
            ->where('p.status = :status')
            ->andWhere('p.nextRetryAt IS NOT NULL')
            ->andWhere('p.nextRetryAt <= :before')
            ->setParameter('status', 'failed')
            ->setParameter('before', $before)
            ->orderBy('p.nextRetryAt', 'ASC');

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn (PaymentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }
}
