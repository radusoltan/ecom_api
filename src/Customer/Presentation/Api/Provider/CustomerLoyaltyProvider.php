<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerLoyaltyResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Customer Loyalty Provider.
 *
 * Provides a customer's loyalty status.
 *
 * @implements ProviderInterface<CustomerLoyaltyResource>
 */
final readonly class CustomerLoyaltyProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerLoyaltyResource
    {
        // Extract customer ID from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query
        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerLoyaltyStatusDTO|null $statusDTO */
        $statusDTO = $handledStamp->getResult();

        if (null === $statusDTO) {
            throw new NotFoundHttpException('Customer loyalty status not found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($statusDTO);
    }

    private function mapDtoToResource(CustomerLoyaltyStatusDTO $dto): CustomerLoyaltyResource
    {
        $resource = new CustomerLoyaltyResource();
        $resource->customerId = $dto->customerId;
        $resource->currentPoints = $dto->currentPoints;
        $resource->currentTier = $dto->currentTier;
        $resource->nextTier = $dto->nextTier;
        $resource->pointsToNextTier = $dto->pointsToNextTier;
        $resource->lifetimePointsEarned = $dto->lifetimePointsEarned;
        $resource->lifetimePointsRedeemed = $dto->lifetimePointsRedeemed;
        $resource->currentTierDiscount = $dto->currentTierDiscount;
        $resource->programName = $dto->programName;
        $resource->programIsActive = $dto->programIsActive;

        return $resource;
    }
}
