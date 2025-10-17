<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetPriceListById\GetPriceListByIdQuery;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PriceListItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PriceListResource
    {
        $priceListIdValue = $uriVariables['id'] ?? null;
        if ($priceListIdValue === null) {
            throw new BadRequestHttpException('Price list ID is required');
        }

        try {
            $priceListId = PriceListId::fromString($priceListIdValue);
        } catch (\InvalidArgumentException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        // Extract tenant ID from context (injected by TenantContextProvider)
        $tenantIdValue = $context['tenant_id'] ?? null;
        if ($tenantIdValue === null) {
            throw new BadRequestHttpException('Tenant ID is required');
        }

        try {
            $tenantId = TenantId::fromString($tenantIdValue);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $envelope = $this->queryBus->dispatch(
            new GetPriceListByIdQuery($priceListId, $tenantId)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $priceListDTO = $handledStamp->getResult();

        if ($priceListDTO === null) {
            return null;
        }

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
    }
}
