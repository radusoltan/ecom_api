<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\CancelAccountDeletion;

use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelAccountDeletionCommandHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {
    }

    public function __invoke(CancelAccountDeletionCommand $command): void
    {
        $request = $this->deletionRequestRepository->findById(
            $command->requestId,
            $command->tenantId
        );

        if (null === $request) {
            throw new \DomainException('Deletion request not found');
        }

        // Verify it belongs to the customer
        if (!$request->customerId()->equals($command->customerId)) {
            throw new \DomainException('Deletion request does not belong to this customer');
        }

        // Cancel the request
        $request->cancel();

        $this->deletionRequestRepository->save($request);

        // Event will be dispatched by repository
        // Event subscriber will send "deletion cancelled" email
    }
}
