<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\AddAddress\AddAddressCommand;
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
 * Add Address Processor.
 *
 * Processes the addition of a new address to a customer.
 */
/**
 * @implements ProcessorInterface<CustomerAddressResource, CustomerAddressResource>
 */
final readonly class AddAddressProcessor implements ProcessorInterface
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

        // Validate required fields
        if (null === $data->street || null === $data->city || null === $data->postalCode || null === $data->country || null === $data->type) {
            throw new BadRequestHttpException('Street, city, postal code, country, and type are required');
        }

        // Create command - the handler generates the address ID internally
        $command = new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: $data->street,
            street2: $data->street2,
            city: $data->city,
            state: $data->state,
            postalCode: $data->postalCode,
            country: $data->country,
            type: $data->type,
            isDefaultShipping: $data->isDefaultShipping ?? false,
            isDefaultBilling: $data->isDefaultBilling ?? false
        );

        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw $exception;
        }

        // Get the generated address ID returned by the handler
        $handledStamp = $envelope->last(HandledStamp::class);
        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for command');
        }

        $addressId = $handledStamp->getResult();
        if (!is_string($addressId)) {
            throw new \RuntimeException('Command handler did not return address ID');
        }

        // Retrieve the created address
        $queryEnvelope = $this->queryBus->dispatch(
            new GetAddressById($addressId, $customerId, $tenantId)
        );

        $queryStamp = $queryEnvelope->last(HandledStamp::class);

        if (!$queryStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerAddressDTO|null $addressDTO */
        $addressDTO = $queryStamp->getResult();

        if (null === $addressDTO) {
            throw new \RuntimeException('Address not found after creation');
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
