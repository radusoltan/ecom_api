<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFeatureFlagQueryHandler
{
    public function __construct(
        private FeatureFlagRepositoryInterface $repository,
    ) {}

    public function __invoke(GetFeatureFlagQuery $query): ?FeatureFlag
    {
        return $this->repository->findByTenantAndFeature(
            TenantId::fromString($query->tenantId),
            $query->featureName,
        );
    }
}
