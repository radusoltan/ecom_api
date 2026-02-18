<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for InspectReturnRequest command.
 */
#[AsMessageHandler]
final readonly class InspectReturnRequestHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository,
    ) {
    }

    public function __invoke(InspectReturnRequest $command): void
    {
        $returnRequest = $this->returnRequestRepository->findById(
            ReturnRequestId::fromString($command->returnRequestId)
        );

        if (null === $returnRequest) {
            throw new \DomainException(sprintf('Return request with ID "%s" not found.', $command->returnRequestId));
        }

        $returnRequest->inspect($command->isResellable, $command->inspectionNotes);

        $this->returnRequestRepository->save($returnRequest);
    }
}
