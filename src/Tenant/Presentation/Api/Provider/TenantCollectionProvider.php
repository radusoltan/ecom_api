<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Provider;

use Symfony\Component\Messenger\Stamp\StampInterface;
use RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Tenant\Application\Query\GetAllTenantsQuery;
use App\Tenant\Presentation\Api\TenantResource;
use App\Tenant\Presentation\Api\Transformer\TenantResourceTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<TenantResource>
 */
final readonly class TenantCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    /**
     * @return array<TenantResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array {
        // Dispatch query to get all tenants
        $query = new GetAllTenantsQuery();
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof StampInterface) {
            throw new RuntimeException('No handler found for query');
        }

        $tenantDTOs = $stamp->getResult();

        if (null === $tenantDTOs) {
            return [];
        }

        return TenantResourceTransformer::fromDTOs($tenantDTOs);
    }
}
