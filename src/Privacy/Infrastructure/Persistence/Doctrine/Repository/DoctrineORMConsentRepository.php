<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<ConsentEntity>
 */
final class DoctrineORMConsentRepository extends ServiceEntityRepository implements ConsentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($registry, ConsentEntity::class);
    }

    public function save(Consent $consent): void
    {
        $entityManager = $this->getEntityManager();

        // Find existing entity or create new one
        $entity = $entityManager->find(ConsentEntity::class, $consent->id()->toString());

        if ($entity === null) {
            $entity = ConsentEntity::fromDomainModel($consent);
            $entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($consent);
        }

        $entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($consent->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(ConsentId $id): ?Consent
    {
        $entity = $this->find($id->toString());

        return $entity?->toDomainModel();
    }

    public function findActiveByCustomerAndPurpose(
        CustomerId $customerId,
        ConsentPurpose $purpose
    ): ?Consent {
        $entity = $this->createQueryBuilder('c')
            ->where('c.customerId = :customerId')
            ->andWhere('c.purpose = :purpose')
            ->andWhere('c.isGranted = true')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('purpose', $purpose->value())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findByCustomerId(CustomerId $customerId): array
    {
        $entities = $this->createQueryBuilder('c')
            ->where('c.customerId = :customerId')
            ->setParameter('customerId', $customerId->toString())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(ConsentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findActiveByCustomerId(CustomerId $customerId): array
    {
        $entities = $this->createQueryBuilder('c')
            ->where('c.customerId = :customerId')
            ->andWhere('c.isGranted = true')
            ->setParameter('customerId', $customerId->toString())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(ConsentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByTenantId(TenantId $tenantId): array
    {
        $entities = $this->createQueryBuilder('c')
            ->where('c.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(ConsentEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function hasActiveConsent(CustomerId $customerId, ConsentPurpose $purpose): bool
    {
        return $this->findActiveByCustomerAndPurpose($customerId, $purpose) !== null;
    }
}
