<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine ORM implementation of PriceListRepositoryInterface.
 */
final readonly class DoctrineORMPriceListRepository implements PriceListRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private CacheService $cacheService,
    ) {
    }

    public function save(PriceList $priceList): void
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $priceList->id()->toString());

        if (null === $entity) {
            // Create new entity
            $entity = PriceListEntity::fromDomainModel($priceList);
            $this->entityManager->persist($entity);
        } else {
            // Update existing entity
            $entity->updateFromDomainModel($priceList);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($priceList->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(PriceListId $id): ?PriceList
    {
        /** @var PriceListEntity|null $entity */
        $entity = $this->entityManager->find(PriceListEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId, bool $activeOnly = false): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'price_lists', [
            'activeOnly' => $activeOnly,
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId, $activeOnly): array {
                $qb = $this->entityManager->createQueryBuilder();

                $qb->select('pl')
                    ->from(PriceListEntity::class, 'pl')
                    ->where('pl.tenantId = :tenantId')
                    ->setParameter('tenantId', $tenantId->toString())
                    ->orderBy('pl.priority', 'DESC')
                    ->addOrderBy('pl.createdAt', 'ASC');

                if ($activeOnly) {
                    $qb->andWhere('pl.isActive = :isActive')
                        ->setParameter('isActive', true);
                }

                $entities = $qb->getQuery()->getResult();

                return array_map(
                    fn (PriceListEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'price_lists')
        );
    }

    public function findValidForTenant(TenantId $tenantId): array
    {
        $now = new \DateTimeImmutable();
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'active_price_lists', [
            'date' => $now->format('Y-m-d H:i'),
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId, $now): array {
                $qb = $this->entityManager->createQueryBuilder();

                $qb->select('pl')
                    ->from(PriceListEntity::class, 'pl')
                    ->where('pl.tenantId = :tenantId')
                    ->andWhere('pl.isActive = :isActive')
                    ->andWhere(
                        $qb->expr()->orX(
                            'pl.validFrom IS NULL',
                            'pl.validFrom <= :now'
                        )
                    )
                    ->andWhere(
                        $qb->expr()->orX(
                            'pl.validTo IS NULL',
                            'pl.validTo >= :now'
                        )
                    )
                    ->setParameter('tenantId', $tenantId->toString())
                    ->setParameter('isActive', true)
                    ->setParameter('now', $now)
                    ->orderBy('pl.priority', 'DESC')
                    ->addOrderBy('pl.createdAt', 'ASC');

                $entities = $qb->getQuery()->getResult();

                return array_map(
                    fn (PriceListEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'price_lists')
        );
    }

    public function findActiveByTenantId(TenantId $tenantId, \DateTimeImmutable $date): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'price_lists_by_date', [
            'date' => $date,
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId, $date): array {
                $qb = $this->entityManager->createQueryBuilder();

                $qb->select('pl')
                    ->from(PriceListEntity::class, 'pl')
                    ->where('pl.tenantId = :tenantId')
                    ->andWhere('pl.isActive = :isActive')
                    ->andWhere(
                        $qb->expr()->orX(
                            'pl.validFrom IS NULL',
                            'pl.validFrom <= :date'
                        )
                    )
                    ->andWhere(
                        $qb->expr()->orX(
                            'pl.validTo IS NULL',
                            'pl.validTo >= :date'
                        )
                    )
                    ->setParameter('tenantId', $tenantId->toString())
                    ->setParameter('isActive', true)
                    ->setParameter('date', $date)
                    ->orderBy('pl.priority', 'DESC')
                    ->addOrderBy('pl.createdAt', 'ASC');

                $entities = $qb->getQuery()->getResult();

                return array_map(
                    fn (PriceListEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'price_lists')
        );
    }

    public function delete(PriceList $priceList): void
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $priceList->id()->toString());

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
