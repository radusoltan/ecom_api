<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ReleaseDeletionHold;

use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Release Deletion Hold Command Handler.
 *
 * Admin command to release a legal/litigation hold on a deletion request.
 */
#[AsMessageHandler]
final readonly class ReleaseDeletionHoldCommandHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {
    }

    public function __invoke(ReleaseDeletionHoldCommand $command): void
    {
        $request = $this->deletionRequestRepository->findById(
            $command->requestId,
            $command->tenantId
        );

        if (null === $request) {
            throw new \DomainException('Deletion request not found');
        }

        // Release the hold
        $request->releaseHold();

        $this->deletionRequestRepository->save($request);
    }
}
