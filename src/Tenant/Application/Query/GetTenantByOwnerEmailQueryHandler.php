<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

use App\Tenant\Domain\Model\Tenant;
use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTenantByOwnerEmailQueryHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {
    }

    public function __invoke(GetTenantByOwnerEmailQuery $query): TenantDTO
    {
        $ownerEmail = Email::fromString($query->ownerEmail);

        $tenant = $this->tenantRepository->findByOwnerEmail($ownerEmail);
        if (!$tenant instanceof Tenant) {
            throw new TenantNotFoundException(sprintf('Tenant with owner email "%s" not found', $query->ownerEmail));
        }

        return TenantDTO::fromAggregate($tenant);
    }
}
