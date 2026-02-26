<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<ShipmentResource> */
final readonly class ShipmentItemProvider implements ProviderInterface
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ShipmentResource
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            return null;
        }

        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';
        $shipment = $this->shipmentRepository->findByIdAndTenant(
            ShipmentId::fromString($id),
            TenantId::fromString($tenantId),
        );

        if (null === $shipment) {
            return null;
        }

        return ShipmentResourceMapper::fromDomain($shipment);
    }
}
