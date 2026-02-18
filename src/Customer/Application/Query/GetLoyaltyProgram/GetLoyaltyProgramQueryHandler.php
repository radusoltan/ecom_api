<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetLoyaltyProgram;

use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Loyalty Program Query Handler.
 *
 * Retrieves the loyalty program for a tenant.
 */
#[AsMessageHandler]
final readonly class GetLoyaltyProgramQueryHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(GetLoyaltyProgramQuery $query): ?LoyaltyProgramDTO
    {
        $tenantId = TenantId::fromString($query->tenantId);

        // Find loyalty program by tenant
        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);

        if (null === $program) {
            return null;
        }

        return LoyaltyProgramDTO::fromDomainModel($program);
    }
}
