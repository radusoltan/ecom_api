<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Presentation\Api\State;

// ReviewId is defined in ProductReview.php, not in its own file
require_once __DIR__.'/../../../../../../src/Review/Domain/Model/ProductReview.php';

use ApiPlatform\Metadata\Operation;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use App\Review\Presentation\Api\State\UpdateReviewProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(UpdateReviewProcessor::class)]
final class UpdateReviewProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private UpdateReviewProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new UpdateReviewProcessor($this->commandBus);
    }

    #[Test]
    public function processDispatchesCommandAndReturnsResource(): void
    {
        $resource = new ProductReviewResource();
        $resource->title = 'Great Product';
        $resource->content = 'I love this product.';

        $operation = $this->createMock(Operation::class);

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process(
            $resource,
            $operation,
            ['id' => '00000000-0000-4000-8000-000000000010'],
        );

        self::assertInstanceOf(ProductReviewResource::class, $result);
        self::assertSame('00000000-0000-4000-8000-000000000010', $result->id);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->updatedAt);
    }

    #[Test]
    public function processThrowsWhenNoReviewId(): void
    {
        $resource = new ProductReviewResource();
        $resource->title = 'Title';
        $resource->content = 'Content';

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Review ID is required');

        $this->processor->process($resource, $operation, []);
    }

    #[Test]
    public function processThrowsWhenNoTitle(): void
    {
        $resource = new ProductReviewResource();
        $resource->title = null;
        $resource->content = 'Content';

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title and content are required');

        $this->processor->process($resource, $operation, ['id' => '00000000-0000-4000-8000-000000000010']);
    }

    #[Test]
    public function processThrowsWhenNoContent(): void
    {
        $resource = new ProductReviewResource();
        $resource->title = 'Title';
        $resource->content = null;

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title and content are required');

        $this->processor->process($resource, $operation, ['id' => '00000000-0000-4000-8000-000000000010']);
    }
}
