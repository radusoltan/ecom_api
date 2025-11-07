<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetActivePriceLists\GetActivePriceListsQuery;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PriceListCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    /**
     * @return array<PriceListResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Extract tenant ID from context (injected by TenantContextProvider)
        $tenantId = TenantId::fromString(
            $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required')
        );

        $envelope = $this->queryBus->dispatch(
            new GetActivePriceListsQuery($tenantId)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $priceListDTOs = $handledStamp->getResult();

        if (!is_array($priceListDTOs)) {
            return [];
        }

        return array_map(function ($priceListDTO) {
            $resource = new PriceListResource();
            $resource->id = $priceListDTO->id;
            $resource->tenantId = $priceListDTO->tenantId;
            $resource->name = $priceListDTO->name;
            $resource->priority = $priceListDTO->priority;
            $resource->rules = $priceListDTO->rules;
            $resource->validFrom = $priceListDTO->validFrom;
            $resource->validTo = $priceListDTO->validTo;
            $resource->isActive = $priceListDTO->isActive;
            $resource->createdAt = $priceListDTO->createdAt;
            $resource->updatedAt = $priceListDTO->updatedAt;

            return $resource;
        }, $priceListDTOs);
    }
}
