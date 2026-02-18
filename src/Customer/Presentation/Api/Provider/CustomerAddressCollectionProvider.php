<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetCustomerAddresses\GetCustomerAddressesQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerAddressResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Customer Address Collection Provider.
 *
 * Provides a collection of addresses for a customer.
 */
/**
 * @implements ProviderInterface<object>
 */
final readonly class CustomerAddressCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    /**
     * @return CustomerAddressResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Optional type filter from query parameters
        $type = $context['filters']['type'] ?? null;

        // Dispatch query
        $query = new GetCustomerAddressesQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            type: $type
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerAddressDTO[] $addressDTOs */
        $addressDTOs = $handledStamp->getResult();

        // Map DTOs to resources
        return array_map(
            fn (CustomerAddressDTO $dto) => $this->mapDtoToResource($dto),
            $addressDTOs
        );
    }

    private function mapDtoToResource(CustomerAddressDTO $dto): CustomerAddressResource
    {
        $resource = new CustomerAddressResource();
        $resource->id = $dto->id;
        $resource->customerId = $dto->customerId;
        $resource->tenantId = $dto->tenantId;
        $resource->street = $dto->street;
        $resource->street2 = $dto->street2;
        $resource->city = $dto->city;
        $resource->state = $dto->state;
        $resource->postalCode = $dto->postalCode;
        $resource->country = $dto->country;
        $resource->type = $dto->type;
        $resource->isDefaultShipping = $dto->isDefaultShipping;
        $resource->isDefaultBilling = $dto->isDefaultBilling;
        $resource->createdAt = $dto->createdAt?->format('c');
        $resource->updatedAt = $dto->updatedAt?->format('c');

        return $resource;
    }
}
