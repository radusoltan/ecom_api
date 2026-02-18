<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerAddressResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Customer Address Item Provider.
 *
 * Provides a single address by its ID.
 */
/**
 * @implements ProviderInterface<CustomerAddressResource>
 */
final readonly class CustomerAddressItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerAddressResource
    {
        // Extract IDs from URI
        if (!isset($uriVariables['customerId']) || !isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Customer ID and address ID are required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);
        $addressId = $uriVariables['id'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query
        $query = new GetAddressById(
            addressId: $addressId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerAddressDTO|null $addressDTO */
        $addressDTO = $handledStamp->getResult();

        if (null === $addressDTO) {
            throw new NotFoundHttpException('Address not found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($addressDTO);
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
