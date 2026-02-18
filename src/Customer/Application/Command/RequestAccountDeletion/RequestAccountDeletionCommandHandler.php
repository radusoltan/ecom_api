<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RequestAccountDeletion;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestAccountDeletionCommandHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {
    }

    public function __invoke(RequestAccountDeletionCommand $command): void
    {
        // Check if there's already a pending request
        $existingRequest = $this->deletionRequestRepository->findPendingByCustomerId(
            $command->customerId,
            $command->tenantId
        );

        if (null !== $existingRequest) {
            throw new \DomainException('A pending account deletion request already exists for this customer');
        }

        // Create new deletion request
        $request = DeletionRequest::create(
            DeletionRequestId::generate(),
            $command->customerId,
            $command->tenantId,
            $command->reason
        );

        $this->deletionRequestRepository->save($request);

        // Event will be dispatched by repository
        // Event subscriber will send confirmation email
    }
}
