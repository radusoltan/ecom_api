<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CreateReturnRequest command.
 *
 * Business Rules:
 * - Return request can only be created for delivered orders (validated in domain or orchestration)
 * - Reason must be at least 10 characters
 */
#[AsMessageHandler]
final readonly class CreateReturnRequestHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository
    ) {
    }

    public function __invoke(CreateReturnRequest $command): void
    {
        $returnRequest = ReturnRequest::create(
            id: ReturnRequestId::fromString($command->returnRequestId),
            tenantId: TenantId::fromString($command->tenantId),
            orderId: OrderId::fromString($command->orderId),
            reason: ReturnReason::fromString($command->reason)
        );

        $this->returnRequestRepository->save($returnRequest);
    }
}
