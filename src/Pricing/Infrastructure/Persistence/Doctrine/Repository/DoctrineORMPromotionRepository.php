<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Repository;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMPromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private CacheService $cacheService,
    ) {
    }

    public function save(Promotion $promotion): void
    {
        $entity = $this->entityManager->find(PromotionEntity::class, $promotion->id()->toString());

        if (null === $entity) {
            $entity = PromotionEntity::fromDomainModel($promotion);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($promotion);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($promotion->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(PromotionId $id, TenantId $tenantId): ?Promotion
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'promotion', [
            'id' => $id->toString(),
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($id, $tenantId): ?Promotion {
                $entity = $this->entityManager->createQueryBuilder()
                    ->select('p')
                    ->from(PromotionEntity::class, 'p')
                    ->where('p.id = :id')
                    ->andWhere('p.tenantId = :tenantId')
                    ->setParameter('id', $id->toString())
                    ->setParameter('tenantId', $tenantId->toString())
                    ->getQuery()
                    ->getOneOrNullResult();

                return $entity?->toDomainModel();
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'promotions')
        );
    }

    public function findByCouponCode(CouponCode $couponCode, TenantId $tenantId): ?Promotion
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'promotion_coupon', [
            'couponCode' => $couponCode->toString(),
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($couponCode, $tenantId): ?Promotion {
                $entity = $this->entityManager->createQueryBuilder()
                    ->select('p')
                    ->from(PromotionEntity::class, 'p')
                    ->where('p.couponCode = :couponCode')
                    ->andWhere('p.tenantId = :tenantId')
                    ->setParameter('couponCode', $couponCode->toString())
                    ->setParameter('tenantId', $tenantId->toString())
                    ->getQuery()
                    ->getOneOrNullResult();

                return $entity?->toDomainModel();
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'promotions')
        );
    }

    public function findActivePromotions(TenantId $tenantId): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'active_promotions', [
            'scope' => 'active',
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId): array {
                $entities = $this->entityManager->createQueryBuilder()
                    ->select('p')
                    ->from(PromotionEntity::class, 'p')
                    ->where('p.tenantId = :tenantId')
                    ->andWhere('p.isActive = true')
                    ->orderBy('p.priority', 'DESC')
                    ->setParameter('tenantId', $tenantId->toString())
                    ->getQuery()
                    ->getResult();

                return array_map(
                    static fn (PromotionEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'promotions')
        );
    }

    public function findAll(TenantId $tenantId): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'promotions', [
            'scope' => 'all',
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId): array {
                $entities = $this->entityManager->createQueryBuilder()
                    ->select('p')
                    ->from(PromotionEntity::class, 'p')
                    ->where('p.tenantId = :tenantId')
                    ->orderBy('p.createdAt', 'DESC')
                    ->setParameter('tenantId', $tenantId->toString())
                    ->getQuery()
                    ->getResult();

                return array_map(
                    static fn (PromotionEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'promotions')
        );
    }

    public function findActiveByTenantId(TenantId $tenantId, \DateTimeImmutable $date): array
    {
        $tenant = $tenantId->toString();
        $key = $this->cacheService->tenantQueryKey($tenant, 'pricing', 'promotions_by_date', [
            'date' => $date,
        ]);

        return $this->cacheService->get(
            $key,
            function () use ($tenantId, $date): array {
                $qb = $this->entityManager->createQueryBuilder();

                $entities = $qb->select('p')
                    ->from(PromotionEntity::class, 'p')
                    ->where('p.tenantId = :tenantId')
                    ->andWhere('p.isActive = true')
                    ->andWhere(
                        $qb->expr()->orX(
                            'p.validFrom IS NULL',
                            'p.validFrom <= :date'
                        )
                    )
                    ->andWhere(
                        $qb->expr()->orX(
                            'p.validTo IS NULL',
                            'p.validTo >= :date'
                        )
                    )
                    ->setParameter('tenantId', $tenantId->toString())
                    ->setParameter('date', $date)
                    ->orderBy('p.priority', 'DESC')
                    ->addOrderBy('p.createdAt', 'ASC')
                    ->getQuery()
                    ->getResult();

                return array_map(
                    static fn (PromotionEntity $entity) => $entity->toDomainModel(),
                    $entities
                );
            },
            300,
            $this->cacheService->tenantScopedTags($tenant, 'promotions')
        );
    }

    public function delete(Promotion $promotion): void
    {
        $entity = $this->entityManager->find(PromotionEntity::class, $promotion->id()->toString());

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
