<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\UpdateReview;
use App\Review\Application\Command\UpdateReviewHandler;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateReviewHandler::class)]
final class UpdateReviewHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private UpdateReviewHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new UpdateReviewHandler($this->repository);
    }

    #[Test]
    public function handleUpdatesContentAndSaves(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildPendingTextReview($reviewId);
        $command = new UpdateReview($reviewId, 'New Title', 'New content body.');

        $this->repository->method('findById')->willReturn($review);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ProductReview $r): bool {
                return 'New Title' === $r->title()
                    && 'New content body.' === $r->content();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenReviewNotFound(): void
    {
        $command = new UpdateReview(ReviewId::generate(), 'Title', 'Content.');

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review not found');

        ($this->handler)($command);
    }

    private function buildPendingTextReview(ReviewId $reviewId): ProductReview
    {
        return ProductReview::submit(
            id: $reviewId,
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: 'cust-001',
            customerName: 'Jane Doe',
            rating: Rating::fromInt(5),
            title: 'Original Title',
            content: 'Original content.',
        );
    }
}
