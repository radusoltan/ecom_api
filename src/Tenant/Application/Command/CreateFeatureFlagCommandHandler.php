<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepositoryInterface $repository,
    ) {}

    public function __invoke(CreateFeatureFlagCommand $command): string
    {
        $tenantId = TenantId::fromString($command->tenantId);

        $existing = $this->repository->findByTenantAndFeature($tenantId, $command->featureName);
        if (null !== $existing) {
            throw new \DomainException(sprintf('Feature flag "%s" already exists for tenant "%s"', $command->featureName, $command->tenantId));
        }

        $id = FeatureFlagId::generate();
        $flag = FeatureFlag::create(
            $id,
            $tenantId,
            $command->featureName,
            $command->enabled,
            $command->configuration,
            $command->description,
        );

        $this->repository->save($flag);

        return $id->toString();
    }
}
