<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetConsentHistory;

use App\Customer\Application\DTO\ConsentHistoryDTO;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Consent History Query Handler.
 *
 * Retrieves paginated consent history records for a customer.
 */
#[AsMessageHandler]
final readonly class GetConsentHistoryQueryHandler
{
    public function __construct(
        private ConsentHistoryRepositoryInterface $consentHistoryRepository,
    ) {
    }

    /**
     * @return array<ConsentHistoryDTO>
     */
    public function __invoke(GetConsentHistoryQuery $query): array
    {
        $historyRecords = $this->consentHistoryRepository->findByCustomerIdPaginated(
            customerId: $query->customerId,
            tenantId: $query->tenantId,
            page: $query->page,
            limit: $query->limit
        );

        return array_map(
            fn ($record) => new ConsentHistoryDTO(
                id: $record->id()->toString(),
                consentType: $record->consentType()->value,
                consentTypeLabel: $record->consentType()->label(),
                granted: $record->granted(),
                ipAddress: $record->ipAddress(),
                userAgent: $record->userAgent(),
                createdAt: $record->createdAt()
            ),
            $historyRecords
        );
    }
}
