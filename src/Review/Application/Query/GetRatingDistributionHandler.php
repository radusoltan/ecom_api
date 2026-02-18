<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\ValueObject\ReviewStatus;
use App\Review\Infrastructure\Persistence\Doctrine\Entity\ProductReviewEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetRatingDistributionHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetRatingDistribution $query): array
    {
        $results = $this->entityManager->createQueryBuilder()
            ->select('r.rating, COUNT(r.id) as count')
            ->from(ProductReviewEntity::class, 'r')
            ->where('r.productId = :productId')
            ->andWhere('r.tenantId = :tenantId')
            ->andWhere('r.status = :status')
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->setParameter('productId', $query->productId->toString())
            ->setParameter('tenantId', $query->tenantId->toString())
            ->setParameter('status', ReviewStatus::APPROVED->value)
            ->getQuery()
            ->getResult();

        // Initialize all ratings to 0
        $distribution = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        // Fill in actual counts
        foreach ($results as $result) {
            $distribution[(int) $result['rating']] = (int) $result['count'];
        }

        return $distribution;
    }
}
