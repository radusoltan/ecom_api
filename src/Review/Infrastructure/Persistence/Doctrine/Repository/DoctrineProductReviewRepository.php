<?php

declare(strict_types=1);

namespace App\Review\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Review\Infrastructure\Persistence\Doctrine\Entity\ProductReviewEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineProductReviewRepository implements ProductReviewRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function save(ProductReview $review): void
    {
        $entity = $this->entityManager->find(ProductReviewEntity::class, $review->id()->toString());

        if (null === $entity) {
            $entity = ProductReviewEntity::fromDomainModel($review);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($review);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($review->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(ReviewId $id): ?ProductReview
    {
        $entity = $this->entityManager->find(ProductReviewEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByProductId(ProductId $productId, TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(ProductReviewEntity::class)
            ->findBy(
                ['productId' => $productId->toString(), 'tenantId' => $tenantId->toString()],
                ['createdAt' => 'DESC']
            );

        return array_map(fn ($entity) => $entity->toDomainModel(), $entities);
    }

    public function findApprovedByProductId(ProductId $productId, TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(ProductReviewEntity::class)
            ->findBy(
                [
                    'productId' => $productId->toString(),
                    'tenantId' => $tenantId->toString(),
                    'status' => ReviewStatus::APPROVED->value,
                ],
                ['createdAt' => 'DESC']
            );

        return array_map(fn ($entity) => $entity->toDomainModel(), $entities);
    }

    public function delete(ProductReview $review): void
    {
        $entity = $this->entityManager->find(ProductReviewEntity::class, $review->id()->toString());

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    public function getAverageRating(ProductId $productId, TenantId $tenantId): ?float
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('AVG(r.rating) as averageRating')
            ->from(ProductReviewEntity::class, 'r')
            ->where('r.productId = :productId')
            ->andWhere('r.tenantId = :tenantId')
            ->andWhere('r.status = :status')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', ReviewStatus::APPROVED->value)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : null;
    }

    public function getReviewCount(ProductId $productId, TenantId $tenantId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(ProductReviewEntity::class, 'r')
            ->where('r.productId = :productId')
            ->andWhere('r.tenantId = :tenantId')
            ->andWhere('r.status = :status')
            ->setParameter('productId', $productId->toString())
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', ReviewStatus::APPROVED->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
