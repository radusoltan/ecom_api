<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Service\FeatureFlagService;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateFeatureFlagConfigCommandHandler
{
    public function __construct(
        private FeatureFlagRepositoryInterface $repository,
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(UpdateFeatureFlagConfigCommand $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $flag = $this->repository->findByTenantAndFeature($tenantId, $command->featureName);

        if (null === $flag) {
            throw new \DomainException(sprintf('Feature flag "%s" not found for tenant "%s"', $command->featureName, $command->tenantId));
        }

        $flag->updateConfiguration($command->configuration);
        $this->repository->save($flag);
        $this->featureFlagService->invalidateCache($tenantId, $command->featureName);
    }
}
