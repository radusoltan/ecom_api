<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SubmitDataSubjectRequestCommandHandler
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository,
    ) {
    }

    public function __invoke(SubmitDataSubjectRequestCommand $command): DataSubjectRequestId
    {
        // Check if there's already a pending erasure request
        if ($command->requestType->equals(RequestType::erasure())) {
            if ($this->requestRepository->hasPendingErasureRequest($command->customerId)) {
                throw new \InvalidArgumentException('A pending erasure request already exists for this customer');
            }
        }

        $request = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $command->tenantId,
            $command->customerId,
            $command->requestType,
            $command->reason
        );

        $this->requestRepository->save($request);

        return $request->id();
    }
}
