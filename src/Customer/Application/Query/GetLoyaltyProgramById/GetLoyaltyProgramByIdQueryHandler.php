<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetLoyaltyProgramById;

use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Loyalty Program By ID Query Handler.
 *
 * Retrieves a loyalty program by its specific ID.
 */
#[AsMessageHandler]
final readonly class GetLoyaltyProgramByIdQueryHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository
    ) {
    }

    public function __invoke(GetLoyaltyProgramByIdQuery $query): ?LoyaltyProgramDTO
    {
        $programId = LoyaltyProgramId::fromString($query->programId);
        $tenantId = TenantId::fromString($query->tenantId);

        // Find loyalty program by ID and tenant
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            return null;
        }

        return LoyaltyProgramDTO::fromDomainModel($program);
    }
}
