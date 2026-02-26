<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<ShipmentResource> */
final readonly class ShipmentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private RequestStack $requestStack,
    ) {
    }

    /** @return ShipmentResource[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';
        $shipments = $this->shipmentRepository->findAllByTenant(TenantId::fromString($tenantId));

        return array_map(fn ($shipment) => ShipmentResourceMapper::fromDomain($shipment), $shipments);
    }
}
