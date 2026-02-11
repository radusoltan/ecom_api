<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ConfirmAccountDeletion;

use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConfirmAccountDeletionCommandHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository
    ) {
    }

    public function __invoke(ConfirmAccountDeletionCommand $command): void
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

        // Confirm the request (starts 30-day countdown)
        $request->confirm();

        $this->deletionRequestRepository->save($request);

        // Event will be dispatched by repository
        // Event subscriber will send "deletion scheduled" email
    }
}
