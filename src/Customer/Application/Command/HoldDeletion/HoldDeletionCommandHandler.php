<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\HoldDeletion;

use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Hold Deletion Command Handler.
 *
 * Admin command to put a deletion request on legal/litigation hold.
 */
#[AsMessageHandler]
final readonly class HoldDeletionCommandHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository
    ) {
    }

    public function __invoke(HoldDeletionCommand $command): void
    {
        $request = $this->deletionRequestRepository->findById(
            $command->requestId,
            $command->tenantId
        );

        if (null === $request) {
            throw new \DomainException('Deletion request not found');
        }

        // Put on hold with reason
        $request->putOnHold($command->holdReason);

        $this->deletionRequestRepository->save($request);

        // Event will be dispatched by repository
    }
}
