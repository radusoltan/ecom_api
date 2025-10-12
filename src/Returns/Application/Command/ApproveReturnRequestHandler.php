<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for ApproveReturnRequest command.
 */
#[AsMessageHandler]
final readonly class ApproveReturnRequestHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository
    ) {
    }

    public function __invoke(ApproveReturnRequest $command): void
    {
        $returnRequest = $this->returnRequestRepository->findById(
            ReturnRequestId::fromString($command->returnRequestId)
        );

        if ($returnRequest === null) {
            throw new \DomainException(
                sprintf('Return request with ID "%s" not found.', $command->returnRequestId)
            );
        }

        $returnRequest->approve();

        $this->returnRequestRepository->save($returnRequest);
    }
}
