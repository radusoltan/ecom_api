<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Review\Infrastructure\Persistence\Doctrine\Entity\ProductReviewEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ProductReviewEntityTest extends TestCase
{
    private ReviewId $reviewId;
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        // ReviewId is defined in the same file as ProductReview; load ProductReview first.
        class_exists(ProductReview::class);
        $this->reviewId = ReviewId::generate();
        $this->productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $createdAt = new \DateTimeImmutable('2026-02-01 09:00:00');
        $updatedAt = new \DateTimeImmutable('2026-02-01 09:00:00');

        $domain = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            '00000000-0000-4000-8000-000000000050',
            'Jane Doe',
            Rating::fromInt(4),
            'Great product',
            'Really happy with this purchase.',
            ReviewStatus::APPROVED,
            true,
            $createdAt,
            $updatedAt
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);

        self::assertSame($this->reviewId->toString(), $entity->getId());
        self::assertSame($this->productId->toString(), $entity->getProductId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertSame('00000000-0000-4000-8000-000000000050', $entity->getCustomerId());
        self::assertSame(4, $entity->getRating());
        self::assertSame('Great product', $entity->getTitle());
        self::assertSame('Really happy with this purchase.', $entity->getContent());
        self::assertSame('approved', $entity->getStatus());
        self::assertTrue($entity->isVerifiedPurchase());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        $restored = $entity->toDomainModel();
        self::assertSame($this->reviewId->toString(), $restored->id()->toString());
        self::assertSame($this->productId->toString(), $restored->productId()->toString());
        self::assertSame($this->tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame('00000000-0000-4000-8000-000000000050', $restored->customerId());
        self::assertSame('Jane Doe', $restored->customerName());
        self::assertSame(4, $restored->rating()->value());
        self::assertSame('Great product', $restored->title());
        self::assertSame('Really happy with this purchase.', $restored->content());
        self::assertSame(ReviewStatus::APPROVED, $restored->status());
        self::assertTrue($restored->isVerifiedPurchase());
    }

    public function testFromDomainModelWithAnonymousRatingOnly(): void
    {
        $domain = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            null,
            null,
            Rating::fromInt(5),
            null,
            null,
            ReviewStatus::PENDING,
            false,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);

        self::assertNull($entity->getCustomerId());
        self::assertSame(5, $entity->getRating());
        self::assertNull($entity->getTitle());
        self::assertNull($entity->getContent());
        self::assertSame('pending', $entity->getStatus());
        self::assertFalse($entity->isVerifiedPurchase());

        $restored = $entity->toDomainModel();
        self::assertNull($restored->customerId());
        self::assertNull($restored->title());
        self::assertNull($restored->content());
    }

    public function testFromDomainModelPendingReview(): void
    {
        $domain = ProductReview::submit(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            '00000000-0000-4000-8000-000000000051',
            'Bob Smith',
            Rating::fromInt(3),
            'Average product',
            'Nothing special about it.',
            false
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);

        self::assertSame('pending', $entity->getStatus());
        self::assertSame(3, $entity->getRating());
        self::assertSame('Average product', $entity->getTitle());
    }

    public function testFromDomainModelWithMinimumRating(): void
    {
        $domain = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            '00000000-0000-4000-8000-000000000052',
            'Unhappy Customer',
            Rating::fromInt(1),
            'Terrible product',
            'Would not recommend this to anyone.',
            ReviewStatus::REJECTED,
            false,
            new \DateTimeImmutable('2026-01-05'),
            new \DateTimeImmutable('2026-01-06')
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);

        self::assertSame(1, $entity->getRating());
        self::assertSame('rejected', $entity->getStatus());

        $restored = $entity->toDomainModel();
        self::assertSame(1, $restored->rating()->value());
        self::assertSame(ReviewStatus::REJECTED, $restored->status());
    }

    public function testUpdateFromDomainModel(): void
    {
        $domain = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            '00000000-0000-4000-8000-000000000053',
            'Alice',
            Rating::fromInt(3),
            'OK product',
            'It was fine.',
            ReviewStatus::PENDING,
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-01')
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);
        self::assertSame('pending', $entity->getStatus());

        $updated = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            '00000000-0000-4000-8000-000000000053',
            'Alice',
            Rating::fromInt(5),
            'Great product after all',
            'Changed my mind, it is excellent!',
            ReviewStatus::APPROVED,
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-10')
        );

        $entity->updateFromDomainModel($updated);

        self::assertSame('approved', $entity->getStatus());
        self::assertSame(5, $entity->getRating());
        self::assertSame('Great product after all', $entity->getTitle());
        self::assertSame('Changed my mind, it is excellent!', $entity->getContent());
    }

    public function testFromDomainModelPreservesTimestamps(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 00:00:00');
        $updatedAt = new \DateTimeImmutable('2026-02-15 12:30:00');

        $domain = ProductReview::reconstituteFromPersistence(
            $this->reviewId,
            $this->productId,
            $this->tenantId,
            null,
            null,
            Rating::fromInt(2),
            null,
            null,
            ReviewStatus::PENDING,
            false,
            $createdAt,
            $updatedAt
        );

        $entity = ProductReviewEntity::fromDomainModel($domain);

        self::assertSame($createdAt->getTimestamp(), $entity->getCreatedAt()->getTimestamp());
    }
}
