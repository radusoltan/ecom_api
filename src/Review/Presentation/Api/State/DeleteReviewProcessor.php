<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Review\Application\Command\DeleteReview;
use App\Review\Domain\Model\ReviewId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $reviewId = $uriVariables['id'] ?? null;
        if (!$reviewId) {
            throw new \InvalidArgumentException('Review ID is required');
        }

        // TODO: Get customer ID from security context when authentication is implemented
        $customerId = null;

        $command = new DeleteReview(
            reviewId: ReviewId::fromString($reviewId),
            customerId: $customerId
        );

        $this->commandBus->dispatch($command);
    }
}
