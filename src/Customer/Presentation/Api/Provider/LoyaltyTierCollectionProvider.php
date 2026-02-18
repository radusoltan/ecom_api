<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Presentation\Api\Resource\LoyaltyTierResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Loyalty Tier Collection Provider.
 *
 * Provides all tiers for a specific loyalty program.
 *
 * @implements ProviderInterface<LoyaltyTierResource>
 */
final readonly class LoyaltyTierCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    /**
     * @return array<int, LoyaltyTierResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Extract program ID from URI
        if (!isset($uriVariables['programId'])) {
            throw new BadRequestHttpException('Program ID is required');
        }

        $programId = $uriVariables['programId'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query to get the loyalty program (which includes tiers)
        $query = new GetLoyaltyProgramByIdQuery($programId, $tenantId->toString());

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var LoyaltyProgramDTO|null $programDTO */
        $programDTO = $handledStamp->getResult();

        if (null === $programDTO) {
            throw new NotFoundHttpException('Loyalty program not found');
        }

        // Map tier DTOs to resources
        return array_map(
            fn ($tierDTO) => $this->mapDtoToResource($tierDTO, $programId),
            $programDTO->tiers
        );
    }

    /**
     * @param \App\Customer\Application\DTO\LoyaltyTierDTO $dto
     */
    private function mapDtoToResource($dto, string $programId): LoyaltyTierResource
    {
        $resource = new LoyaltyTierResource();
        $resource->id = $dto->id;
        $resource->programId = $programId;
        $resource->name = $dto->name;
        $resource->threshold = $dto->threshold;
        $resource->discountPercentage = $dto->discountPercentage;
        $resource->freeShippingMinOrder = $dto->freeShippingMinOrder;
        $resource->sortOrder = $dto->sortOrder;

        return $resource;
    }
}
