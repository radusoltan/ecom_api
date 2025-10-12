<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTenantCommandHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {
    }

    public function __invoke(UpdateTenantCommand $command): void
    {
        $tenantId = TenantId::fromString($command->id);
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \RuntimeException('Tenant not found');
        }

        $tenant->update(
            TenantName::fromString($command->name),
            Email::fromString($command->ownerEmail)
        );

        $this->tenantRepository->save($tenant);
    }
}
