<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\Query\GetLoyaltyProgram\GetLoyaltyProgramQuery;
use App\Customer\Presentation\Api\Resource\LoyaltyProgramResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Loyalty Program Provider.
 *
 * Provides the loyalty program for the current tenant.
 *
 * @implements ProviderInterface<LoyaltyProgramResource>
 */
final readonly class LoyaltyProgramProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LoyaltyProgramResource
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query
        $query = new GetLoyaltyProgramQuery($tenantId->toString());

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var LoyaltyProgramDTO|null $programDTO */
        $programDTO = $handledStamp->getResult();

        if (null === $programDTO) {
            throw new NotFoundHttpException('No loyalty program found for this tenant');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($programDTO);
    }

    private function mapDtoToResource(LoyaltyProgramDTO $dto): LoyaltyProgramResource
    {
        $resource = new LoyaltyProgramResource();
        $resource->id = $dto->id;
        $resource->tenantId = $dto->tenantId;
        $resource->name = $dto->name;
        $resource->description = $dto->description;
        $resource->earningRate = $dto->earningRate;
        $resource->minOrderValue = $dto->minOrderValue;
        $resource->redemptionRule = $dto->redemptionRule;
        $resource->validityDays = $dto->validityDays;
        $resource->isActive = $dto->isActive;
        $resource->tiers = array_map(fn ($tier) => [
            'id' => $tier->id,
            'name' => $tier->name,
            'threshold' => $tier->threshold,
            'discountPercentage' => $tier->discountPercentage,
            'freeShippingMinOrder' => $tier->freeShippingMinOrder,
            'sortOrder' => $tier->sortOrder,
        ], $dto->tiers);
        $resource->createdAt = $dto->createdAt;
        $resource->updatedAt = $dto->updatedAt;

        return $resource;
    }
}
