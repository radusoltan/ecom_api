<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\SubmitReview;
use App\Review\Application\Command\SubmitReviewHandler;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmitReviewHandler::class)]
final class SubmitReviewHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private SubmitReviewHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new SubmitReviewHandler($this->repository);
    }

    #[Test]
    public function handleCreatesAndSavesReview(): void
    {
        $command = new SubmitReview(
            reviewId: ReviewId::generate(),
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ProductReview $review) use ($command): bool {
                return $review->id()->equals($command->reviewId)
                    && 4 === $review->rating()->value()
                    && $review->status()->isPending();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleWithVerifiedPurchaseSetsFlag(): void
    {
        $command = new SubmitReview(
            reviewId: ReviewId::generate(),
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: 'cust-001',
            customerName: 'Jane',
            rating: Rating::fromInt(5),
            isVerifiedPurchase: true,
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (ProductReview $review) => $review->isVerifiedPurchase(),
            ));

        ($this->handler)($command);
    }
}
