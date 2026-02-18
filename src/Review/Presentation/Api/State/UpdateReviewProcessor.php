<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Review\Application\Command\UpdateReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UpdateReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductReviewResource
    {
        assert($data instanceof ProductReviewResource);

        $reviewId = $uriVariables['id'] ?? null;
        if (!$reviewId) {
            throw new \InvalidArgumentException('Review ID is required');
        }

        if (!$data->title || !$data->content) {
            throw new \InvalidArgumentException('Title and content are required');
        }

        $command = new UpdateReview(
            reviewId: ReviewId::fromString($reviewId),
            title: $data->title,
            content: $data->content
        );

        $this->commandBus->dispatch($command);

        $data->id = $reviewId;
        $data->updatedAt = new \DateTimeImmutable();

        return $data;
    }
}
