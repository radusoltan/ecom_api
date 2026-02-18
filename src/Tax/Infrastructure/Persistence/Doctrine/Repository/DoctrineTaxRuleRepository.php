<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Doctrine implementation of TaxRuleRepository.
 *
 * Converts between domain models (TaxRule) and Doctrine entities (TaxRuleEntity).
 * Dispatches domain events after persistence.
 *
 * Business Rules:
 * - Rules are filtered by tenant (multi-tenancy enforcement)
 * - Only active rules are returned for findApplicableRules
 * - Validity dates are checked (validFrom <= asOfDate <= validTo)
 * - Rules are sorted by priority descending (highest priority wins)
 * - Domain events are dispatched after successful persistence
 */
final class DoctrineTaxRuleRepository implements TaxRuleRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function save(TaxRule $taxRule): void
    {
        // Find existing entity or create new one
        $entity = $this->entityManager->find(TaxRuleEntity::class, $taxRule->id()->toString());

        if (null !== $entity) {
            // Update existing entity
            $entity->updateFromDomainModel($taxRule);
        } else {
            // Create new entity
            $entity = TaxRuleEntity::fromDomainModel($taxRule);
            $this->entityManager->persist($entity);
        }

        // Flush changes to database
        $this->entityManager->flush();

        // Dispatch domain events after successful persistence
        foreach ($taxRule->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(TaxRuleId $id): ?TaxRule
    {
        $entity = $this->entityManager->find(TaxRuleEntity::class, $id->toString());

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findByTenantId(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(TaxRuleEntity::class);

        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC']
        );

        return array_map(
            static fn (TaxRuleEntity $entity): TaxRule => $entity->toDomainModel(),
            $entities
        );
    }

    public function findApplicableRules(
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        \DateTimeImmutable $asOfDate,
    ): array {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.tenantId = :tenantId')
            ->andWhere('t.countryCode = :countryCode')
            ->andWhere('t.category = :category')
            ->andWhere('t.isActive = :isActive')
            ->andWhere('t.validFrom <= :asOfDate')
            ->andWhere('t.validTo IS NULL OR t.validTo >= :asOfDate')
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('countryCode', $jurisdiction->countryCode())
            ->setParameter('category', $category->value)
            ->setParameter('isActive', true)
            ->setParameter('asOfDate', $asOfDate);

        // Region code filter: match NULL (country-level) OR exact region match
        $regionCode = $jurisdiction->regionCode();
        if (null !== $regionCode) {
            // Match either country-level rules (region_code IS NULL) OR exact region match
            $qb->andWhere('t.regionCode IS NULL OR t.regionCode = :regionCode')
                ->setParameter('regionCode', $regionCode);
        } else {
            // Only country-level rules (region_code IS NULL)
            $qb->andWhere('t.regionCode IS NULL');
        }

        $entities = $qb->getQuery()->getResult();

        return array_map(
            static fn (TaxRuleEntity $entity): TaxRule => $entity->toDomainModel(),
            $entities
        );
    }

    public function findBestMatchingRule(
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        \DateTimeImmutable $asOfDate,
    ): ?TaxRule {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->from(TaxRuleEntity::class, 't')
            ->where('t.tenantId = :tenantId')
            ->andWhere('t.countryCode = :countryCode')
            ->andWhere('t.category = :category')
            ->andWhere('t.isActive = :isActive')
            ->andWhere('t.validFrom <= :asOfDate')
            ->andWhere('t.validTo IS NULL OR t.validTo >= :asOfDate')
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('countryCode', $jurisdiction->countryCode())
            ->setParameter('category', $category->value)
            ->setParameter('isActive', true)
            ->setParameter('asOfDate', $asOfDate);

        // Region code filter: prefer exact region match, fallback to country-level
        $regionCode = $jurisdiction->regionCode();
        if (null !== $regionCode) {
            // Prefer region-specific rules (ORDER BY will handle priority)
            $qb->andWhere('t.regionCode IS NULL OR t.regionCode = :regionCode')
                ->setParameter('regionCode', $regionCode)
                // Add region match priority: region-specific rules before country-level
                ->addOrderBy('t.regionCode', 'DESC'); // NULL sorts first, region code sorts last
        } else {
            // Only country-level rules
            $qb->andWhere('t.regionCode IS NULL');
        }

        $entity = $qb->getQuery()->getOneOrNullResult();

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function delete(TaxRuleId $id): void
    {
        $entity = $this->entityManager->find(TaxRuleEntity::class, $id->toString());

        if (null === $entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function nextIdentity(): TaxRuleId
    {
        return TaxRuleId::generate();
    }
}
