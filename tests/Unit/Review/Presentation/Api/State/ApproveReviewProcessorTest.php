<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Patch;
use App\Review\Application\Command\ApproveReview;
use App\Review\Domain\Model\ProductReview;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use App\Review\Presentation\Api\State\ApproveReviewProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ApproveReviewProcessor::class)]
final class ApproveReviewProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private ApproveReviewProcessor $processor;

    protected function setUp(): void
    {
        // ReviewId is defined inside ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new ApproveReviewProcessor($this->commandBus);
    }

    #[Test]
    public function processDispatchesApproveCommand(): void
    {
        $reviewId = '550e8400-e29b-41d4-a716-446655440000';

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (ApproveReview $cmd) => $cmd->reviewId->toString() === $reviewId
            ))
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process(
            new ProductReviewResource(),
            new Patch(),
            ['id' => $reviewId],
        );

        self::assertInstanceOf(ProductReviewResource::class, $result);
        self::assertSame($reviewId, $result->id);
        self::assertSame('approved', $result->status);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->updatedAt);
    }

    #[Test]
    public function processThrowsWhenReviewIdMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Review ID is required');

        $this->processor->process(
            new ProductReviewResource(),
            new Patch(),
            [],
        );
    }
}
