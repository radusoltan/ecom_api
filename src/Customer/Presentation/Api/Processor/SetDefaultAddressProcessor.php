<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\SetDefaultAddress\SetDefaultAddressCommand;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerAddressResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Set Default Address Processor.
 *
 * Processes setting an address as default for shipping or billing.
 */
/**
 * @implements ProcessorInterface<CustomerAddressResource, CustomerAddressResource>
 */
final readonly class SetDefaultAddressProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerAddressResource
    {
        if (!$data instanceof CustomerAddressResource) {
            throw new BadRequestHttpException('Expected CustomerAddressResource');
        }

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

        // Get type from request body
        if (null === $data->type) {
            throw new BadRequestHttpException('Type (shipping or billing) is required');
        }

        // Validate type
        if (!\in_array($data->type, ['shipping', 'billing'], true)) {
            throw new BadRequestHttpException('Type must be either "shipping" or "billing"');
        }

        // Create command
        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId,
            type: $data->type
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw $exception;
        }

        // Retrieve the updated address
        $envelope = $this->queryBus->dispatch(
            new GetAddressById($addressId, $customerId, $tenantId)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerAddressDTO|null $addressDTO */
        $addressDTO = $handledStamp->getResult();

        if (null === $addressDTO) {
            throw new \RuntimeException('Address not found after update');
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
