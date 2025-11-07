<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CompleteReturnRequest command.
 *
 * Business Rules:
 * - Refund amount must be ≤ original order amount (validated here or in orchestration)
 * - Triggers refund process via Payment bounded context
 */
#[AsMessageHandler]
final readonly class CompleteReturnRequestHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository
    ) {
    }

    public function __invoke(CompleteReturnRequest $command): void
    {
        $returnRequest = $this->returnRequestRepository->findById(
            ReturnRequestId::fromString($command->returnRequestId)
        );

        if (null === $returnRequest) {
            throw new \DomainException(sprintf('Return request with ID "%s" not found.', $command->returnRequestId));
        }

        $refundAmount = Money::fromScalars($command->refundAmount, $command->refundCurrency);

        $returnRequest->complete($refundAmount);

        $this->returnRequestRepository->save($returnRequest);
    }
}
