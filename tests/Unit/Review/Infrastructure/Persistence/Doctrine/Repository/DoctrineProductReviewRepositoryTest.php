<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Review\Infrastructure\Persistence\Doctrine\Entity\ProductReviewEntity;
use App\Review\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductReviewRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DoctrineProductReviewRepository::class)]
#[Group('review')]
final class DoctrineProductReviewRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $eventBus;
    private DoctrineProductReviewRepository $repository;

    private const TENANT_UUID = '00000000-0000-4000-8000-000000000001';
    private const PRODUCT_UUID = '11111111-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->repository = new DoctrineProductReviewRepository(
            $this->entityManager,
            $this->eventBus,
        );
    }

    // ---------------------------------------------------------------
    // Implements correct interface
    // ---------------------------------------------------------------

    #[Test]
    public function itImplementsProductReviewRepositoryInterface(): void
    {
        self::assertInstanceOf(ProductReviewRepositoryInterface::class, $this->repository);
    }

    // ---------------------------------------------------------------
    // save – new entity
    // ---------------------------------------------------------------

    #[Test]
    public function itPersistsNewReviewWhenEntityDoesNotExist(): void
    {
        $review = $this->buildReview();

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $review->id()->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(ProductReviewEntity::class));

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->eventBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->repository->save($review);
    }

    // ---------------------------------------------------------------
    // save – existing entity (update path)
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdatesExistingReviewEntityWhenEntityExists(): void
    {
        $review = $this->buildReview();

        $existingEntity = $this->createMock(ProductReviewEntity::class);
        $existingEntity->expects(self::once())
            ->method('updateFromDomainModel')
            ->with($review);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $review->id()->toString())
            ->willReturn($existingEntity);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->eventBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->repository->save($review);
    }

    // ---------------------------------------------------------------
    // findById – found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsDomainModelWhenReviewFound(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildReview($reviewId);

        $entity = $this->createMock(ProductReviewEntity::class);
        $entity->method('toDomainModel')->willReturn($review);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $reviewId->toString())
            ->willReturn($entity);

        $result = $this->repository->findById($reviewId);

        self::assertSame($review, $result);
    }

    // ---------------------------------------------------------------
    // findById – not found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenReviewNotFound(): void
    {
        $reviewId = ReviewId::generate();

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $reviewId->toString())
            ->willReturn(null);

        $result = $this->repository->findById($reviewId);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // findByProductId
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsMappedReviewsForProduct(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_UUID);
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $review = $this->buildReview();

        $entity = $this->createMock(ProductReviewEntity::class);
        $entity->method('toDomainModel')->willReturn($review);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findBy')
            ->with(
                ['productId' => $productId->toString(), 'tenantId' => $tenantId->toString()],
                ['createdAt' => 'DESC']
            )
            ->willReturn([$entity]);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductReviewEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByProductId($productId, $tenantId);

        self::assertCount(1, $result);
        self::assertSame($review, $result[0]);
    }

    // ---------------------------------------------------------------
    // findApprovedByProductId
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsOnlyApprovedReviewsForProduct(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_UUID);
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $review = $this->buildReview();

        $entity = $this->createMock(ProductReviewEntity::class);
        $entity->method('toDomainModel')->willReturn($review);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findBy')
            ->with(
                [
                    'productId' => $productId->toString(),
                    'tenantId' => $tenantId->toString(),
                    'status' => ReviewStatus::APPROVED->value,
                ],
                ['createdAt' => 'DESC']
            )
            ->willReturn([$entity]);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductReviewEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findApprovedByProductId($productId, $tenantId);

        self::assertCount(1, $result);
    }

    // ---------------------------------------------------------------
    // delete – entity found
    // ---------------------------------------------------------------

    #[Test]
    public function itDeletesReviewWhenEntityExists(): void
    {
        $review = $this->buildReview();
        $entity = $this->createMock(ProductReviewEntity::class);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $review->id()->toString())
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('remove')
            ->with($entity);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->repository->delete($review);
    }

    // ---------------------------------------------------------------
    // delete – entity not found (no-op)
    // ---------------------------------------------------------------

    #[Test]
    public function itDoesNothingWhenDeletingNonExistentReview(): void
    {
        $review = $this->buildReview();

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductReviewEntity::class, $review->id()->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::never())
            ->method('remove');

        $this->entityManager->expects(self::never())
            ->method('flush');

        $this->repository->delete($review);
    }

    // ---------------------------------------------------------------
    // getAverageRating
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsAverageRatingForApprovedReviews(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_UUID);
        $tenantId = TenantId::fromString(self::TENANT_UUID);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('4.5');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getAverageRating($productId, $tenantId);

        self::assertSame(4.5, $result);
    }

    // ---------------------------------------------------------------
    // getAverageRating – null result
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullAverageRatingWhenNoApprovedReviews(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_UUID);
        $tenantId = TenantId::fromString(self::TENANT_UUID);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->getAverageRating($productId, $tenantId);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // getReviewCount
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsReviewCountForProduct(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_UUID);
        $tenantId = TenantId::fromString(self::TENANT_UUID);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('3');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->getReviewCount($productId, $tenantId);

        self::assertSame(3, $result);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildReview(?ReviewId $id = null): ProductReview
    {
        return ProductReview::reconstituteFromPersistence(
            id: $id ?? ReviewId::generate(),
            productId: ProductId::fromString(self::PRODUCT_UUID),
            tenantId: TenantId::fromString(self::TENANT_UUID),
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
            title: null,
            content: null,
            status: ReviewStatus::PENDING,
            isVerifiedPurchase: false,
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );
    }
}
