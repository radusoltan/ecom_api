<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMFlashSaleRepository implements FlashSaleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(FlashSale $flashSale): void
    {
        $entity = $this->entityManager->find(FlashSaleEntity::class, $flashSale->id()->toString());

        if (null === $entity) {
            $entity = FlashSaleEntity::fromDomainModel($flashSale);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($flashSale);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($flashSale->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(FlashSaleId $id, TenantId $tenantId): ?FlashSale
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.id = :id')
            ->andWhere('f.tenantId = :tenantId')
            ->setParameter('id', $id->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findActiveByTenant(TenantId $tenantId): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.tenantId = :tenantId')
            ->andWhere('f.status = :status')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', 'active')
            ->orderBy('f.startTime', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FlashSaleEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findUpcoming(TenantId $tenantId): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.tenantId = :tenantId')
            ->andWhere('f.status = :status')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', 'scheduled')
            ->orderBy('f.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FlashSaleEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findActiveByProductId(ProductId $productId, TenantId $tenantId): ?FlashSale
    {
        $qb = $this->entityManager->createQueryBuilder();

        $entity = $qb->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.tenantId = :tenantId')
            ->andWhere('f.status = :status')
            ->andWhere($qb->expr()->like('f.productIds', ':productId'))
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', 'active')
            ->setParameter('productId', '%"' . $productId->toString() . '"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findAll(TenantId $tenantId): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.tenantId = :tenantId')
            ->orderBy('f.createdAt', 'DESC')
            ->setParameter('tenantId', $tenantId->toString())
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FlashSaleEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findScheduledToActivateAt(\DateTimeImmutable $dateTime): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.status = :status')
            ->andWhere('f.startTime <= :dateTime')
            ->setParameter('status', 'scheduled')
            ->setParameter('dateTime', $dateTime)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FlashSaleEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findActiveToEndAt(\DateTimeImmutable $dateTime): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FlashSaleEntity::class, 'f')
            ->where('f.status = :status')
            ->andWhere('f.endTime <= :dateTime')
            ->setParameter('status', 'active')
            ->setParameter('dateTime', $dateTime)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FlashSaleEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(FlashSale $flashSale): void
    {
        $entity = $this->entityManager->find(FlashSaleEntity::class, $flashSale->id()->toString());

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
