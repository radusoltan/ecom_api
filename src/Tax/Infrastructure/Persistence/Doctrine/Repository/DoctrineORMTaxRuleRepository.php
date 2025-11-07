<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine ORM Tax Rule Repository.
 */
final readonly class DoctrineORMTaxRuleRepository implements TaxRuleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function save(TaxRule $taxRule): void
    {
        $repository = $this->entityManager->getRepository(TaxRuleEntity::class);
        $entity = $repository->find($taxRule->id()->toString());

        if (null === $entity) {
            // Create new
            $entity = TaxRuleEntity::fromDomainModel($taxRule);
            $this->entityManager->persist($entity);
        } else {
            // Update existing
            $entity->updateFromDomainModel($taxRule);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($taxRule->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(TaxRuleId $id, TenantId $tenantId): ?TaxRule
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('id', $id->toString())
            ->setParameter('tenantId', $tenantId->toString());

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findByJurisdiction(TaxJurisdiction $jurisdiction, TenantId $tenantId): ?TaxRule
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.tenantId = :tenantId')
            ->andWhere('t.isActive = :active')
            ->andWhere('t.countryCode = :countryCode')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('active', true)
            ->setParameter('countryCode', $jurisdiction->getCountryCode());

        if ($jurisdiction->hasRegion()) {
            $qb->andWhere('(t.regionCode = :regionCode OR t.regionCode IS NULL)')
                ->setParameter('regionCode', $jurisdiction->getRegionCode())
                // Order by CASE to put NULL last (specific region first)
                ->addOrderBy('CASE WHEN t.regionCode IS NULL THEN 1 ELSE 0 END', 'ASC');
        } else {
            $qb->andWhere('t.regionCode IS NULL');
        }

        $entity = $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();

        return $entity?->toDomainModel();
    }

    public function findAll(TenantId $tenantId, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $entities = $qb->getQuery()->getResult();

        return array_map(fn ($entity) => $entity->toDomainModel(), $entities);
    }

    public function findActive(TenantId $tenantId): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.tenantId = :tenantId')
            ->andWhere('t.isActive = :active')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('active', true)
            ->orderBy('t.countryCode', 'ASC')
            ->addOrderBy('t.regionCode', 'ASC');

        $entities = $qb->getQuery()->getResult();

        return array_map(fn ($entity) => $entity->toDomainModel(), $entities);
    }
}
