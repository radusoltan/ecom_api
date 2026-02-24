<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Review\Application\Command\ApproveReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<ProductReviewResource, ProductReviewResource>
 */
final readonly class ApproveReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductReviewResource
    {
        $reviewId = $uriVariables['id'] ?? null;
        if (!$reviewId) {
            throw new \InvalidArgumentException('Review ID is required');
        }

        $command = new ApproveReview(
            reviewId: ReviewId::fromString($reviewId),
        );

        $this->commandBus->dispatch($command);

        $resource = new ProductReviewResource();
        $resource->id = $reviewId;
        $resource->status = 'approved';
        $resource->updatedAt = new \DateTimeImmutable();

        return $resource;
    }
}
