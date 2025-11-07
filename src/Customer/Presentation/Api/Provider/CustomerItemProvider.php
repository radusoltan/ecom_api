<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByIdQuery;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<CustomerEntity>
 */
final readonly class CustomerItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CustomerEntity
    {
        $customerId = $uriVariables['id'] ?? throw new \InvalidArgumentException('Customer ID is required');

        // Get tenant ID from context (set by TenantContextProvider or similar)
        $tenantId = $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required');

        $envelope = $this->queryBus->dispatch(new GetCustomerByIdQuery($customerId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $customerDTO = $handledStamp->getResult();

        if (null === $customerDTO) {
            return null;
        }

        // Convert DTO to entity for API Platform
        return $this->populateEntityFromDTO(new CustomerEntity(), $customerDTO);
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
