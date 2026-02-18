<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteTenantCommandHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(DeleteTenantCommand $command): void
    {
        $tenantId = TenantId::fromString($command->id);
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \RuntimeException('Tenant not found');
        }

        $this->tenantRepository->delete($tenant);
    }
}
