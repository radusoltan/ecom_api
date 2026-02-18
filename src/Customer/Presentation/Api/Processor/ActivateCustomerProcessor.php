<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\ActivateCustomerCommand;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByIdQuery;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<CustomerEntity, CustomerEntity>
 */
final readonly class ActivateCustomerProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerEntity
    {
        if (!$data instanceof CustomerEntity) {
            throw new \InvalidArgumentException('Expected CustomerEntity');
        }
        // Get tenant ID from context (injected by TenantContextProcessor decorator)
        if (!isset($context['tenant_id'])) {
            throw new \RuntimeException('Tenant ID is required');
        }

        $customerId = $uriVariables['id'] ?? throw new \InvalidArgumentException('Customer ID is required');
        $tenantId = $context['tenant_id'];

        $command = new ActivateCustomerCommand(
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);

        // Retrieve the updated customer
        $envelope = $this->queryBus->dispatch(new GetCustomerByIdQuery($customerId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $customerDTO = $handledStamp->getResult();

        if (null === $customerDTO) {
            throw new \RuntimeException('Customer not found after activation');
        }

        // Convert DTO back to entity for API Platform
        $entity = new CustomerEntity();
        $entity = $this->populateEntityFromDTO($entity, $customerDTO);

        return $entity;
    }

    private function populateEntityFromDTO(CustomerEntity $entity, CustomerDTO $dto): CustomerEntity
    {
        // Use setters to populate entity for API response
        $entity->setId($dto->id);
        $entity->setTenantId($dto->tenantId);
        $entity->setEmail($dto->email);
        $entity->setFirstName($dto->firstName);
        $entity->setLastName($dto->lastName);
        $entity->setPhoneNumber($dto->phoneNumber);
        $entity->setSegment($dto->segment);
        $entity->setLoyaltyPoints($dto->loyaltyPoints);
        $entity->setIsActive($dto->isActive);
        $entity->setCreatedAt(new \DateTimeImmutable($dto->createdAt));
        $entity->setUpdatedAt(new \DateTimeImmutable($dto->updatedAt));

        return $entity;
    }
}
