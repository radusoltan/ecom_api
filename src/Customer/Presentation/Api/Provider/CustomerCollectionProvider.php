<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetAllCustomersQuery;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<CustomerEntity>
 */
final readonly class CustomerCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    /**
     * @return CustomerEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get tenant ID from context (set by TenantContextProvider or similar)
        $tenantId = $context['tenant_id'] ?? throw new InvalidArgumentException('Tenant ID is required');

        // Get optional segment filter from query parameters
        $segment = $context['filters']['segment'] ?? null;

        $envelope = $this->queryBus->dispatch(new GetAllCustomersQuery($tenantId, $segment));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $customerDTOs = $handledStamp->getResult();

        if ($customerDTOs === null || !is_array($customerDTOs)) {
            return [];
        }

        // Convert DTOs to entities for API Platform
        return array_map(
            fn($dto) => $this->populateEntityFromDTO(new CustomerEntity(), $dto),
            $customerDTOs
        );
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
