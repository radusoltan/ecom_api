<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\DataExportRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine Implementation of Data Export Request Repository.
 */
final readonly class DoctrineDataExportRequestRepository implements DataExportRequestRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(DataExportRequest $request): void
    {
        $entity = $this->entityManager->find(DataExportRequestEntity::class, $request->id()->toString());

        if (null === $entity) {
            // Create new entity
            $entity = DataExportRequestEntity::fromDomainModel($request);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity
            $entity->updateFromDomainModel($request);
        }

        $this->entityManager->flush();
    }

    public function findById(DataExportRequestId $id): ?DataExportRequest
    {
        $entity = $this->entityManager->find(DataExportRequestEntity::class, $id->toString());

        return $entity ? $entity->toDomainModel() : null;
    }

    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(DataExportRequestEntity::class)->findBy([
            'customerId' => $customerId->toString(),
            'tenantId' => $tenantId->toString(),
        ], ['createdAt' => 'DESC']);

        return array_map(
            fn (DataExportRequestEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findPendingByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DataExportRequest
    {
        $entity = $this->entityManager->getRepository(DataExportRequestEntity::class)->findOneBy([
            'customerId' => $customerId->toString(),
            'tenantId' => $tenantId->toString(),
            'status' => ExportStatus::PENDING->value,
        ]);

        return $entity ? $entity->toDomainModel() : null;
    }

    public function countRecentByCustomerId(CustomerId $customerId, \DateTimeImmutable $since): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return (int) $qb->select('COUNT(e.id)')
            ->from(DataExportRequestEntity::class, 'e')
            ->where('e.customerId = :customerId')
            ->andWhere('e.createdAt >= :since')
            ->setParameter('customerId', $customerId->toString())
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
