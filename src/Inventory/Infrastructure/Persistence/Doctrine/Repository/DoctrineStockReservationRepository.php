<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockReservationEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockReservationEntity>
 */
final class DoctrineStockReservationRepository extends ServiceEntityRepository implements StockReservationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockReservationEntity::class);
    }

    public function save(StockReservation $reservation): void
    {
        $qb = $this->createQueryBuilder('r');
        $qb->where('r.id = :id')
            ->setParameter('id', $reservation->reservationId());

        $existingEntity = $qb->getQuery()->getOneOrNullResult();

        if ($existingEntity instanceof StockReservationEntity) {
            $existingEntity->updateFromDomainModel($reservation);
        } else {
            $entity = StockReservationEntity::fromDomainModel($reservation);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();
    }

    public function findByReservationId(string $reservationId): ?StockReservation
    {
        $qb = $this->createQueryBuilder('r');
        $qb->where('r.id = :id')
            ->setParameter('id', $reservationId);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findExpiredReservations(?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('r');
        $qb->where('r.expiresAt <= :now')
            ->andWhere('r.isReleased = :released')
            ->setParameter('now', $now)
            ->setParameter('released', false);

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function findActiveByTenant(TenantId $tenantId): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('r');
        $qb->where('r.tenantId = :tenantId')
            ->andWhere('r.isReleased = :released')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('released', false)
            ->setParameter('now', $now);

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $entity->toDomainModel(), $entities);
    }

    public function delete(StockReservation $reservation): void
    {
        $qb = $this->createQueryBuilder('r');
        $qb->where('r.id = :id')
            ->setParameter('id', $reservation->reservationId());

        $entity = $qb->getQuery()->getOneOrNullResult();

        if ($entity !== null) {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        }
    }
}
