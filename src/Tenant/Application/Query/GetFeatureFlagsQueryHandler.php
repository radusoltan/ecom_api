<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFeatureFlagsQueryHandler
{
    public function __construct(
        private FeatureFlagRepositoryInterface $repository,
    ) {
    }

    /** @return FeatureFlag[] */
    public function __invoke(GetFeatureFlagsQuery $query): array
    {
        return $this->repository->findAllByTenant(
            TenantId::fromString($query->tenantId),
        );
    }
}
