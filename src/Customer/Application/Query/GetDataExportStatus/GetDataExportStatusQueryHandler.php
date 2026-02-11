<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetDataExportStatus;

use App\Customer\Application\DTO\DataExportStatusDTO;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Data Export Status Query Handler.
 *
 * Returns the current status of a data export request.
 * Verifies that the request belongs to the specified customer.
 */
#[AsMessageHandler]
final readonly class GetDataExportStatusQueryHandler
{
    public function __construct(
        private DataExportRequestRepositoryInterface $exportRequestRepository
    ) {
    }

    public function __invoke(GetDataExportStatusQuery $query): ?DataExportStatusDTO
    {
        $requestId = DataExportRequestId::fromString($query->requestId);
        $customerId = CustomerId::fromString($query->customerId);

        $request = $this->exportRequestRepository->findById($requestId);

        if (null === $request) {
            return null;
        }

        // Verify ownership
        if (!$request->customerId()->equals($customerId)) {
            throw new \InvalidArgumentException('Export request does not belong to this customer');
        }

        return new DataExportStatusDTO(
            id: $request->id()->toString(),
            status: $request->status()->value,
            expiresAt: $request->expiresAt(),
            createdAt: $request->createdAt(),
            completedAt: $request->completedAt(),
            isDownloadable: $request->isDownloadable(),
            errorMessage: $request->errorMessage()
        );
    }
}
