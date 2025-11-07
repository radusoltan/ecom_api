<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByEmailQuery;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Profile Provider - returns authenticated user's customer data.
 *
 * Endpoint: GET /api/profile
 * Returns: CustomerEntity of the authenticated user
 *
 * @implements ProviderInterface<CustomerEntity>
 */
final readonly class ProfileProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CustomerEntity
    {
        // Get authenticated user
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        // Get user email from security token
        $email = $user->getUserIdentifier(); // This is the email

        // Get tenant ID from context or from header
        $tenantId = $context['tenant_id'] ?? null;

        if (null === $tenantId) {
            $request = $this->requestStack->getCurrentRequest();
            $tenantId = $request?->headers->get('X-Tenant-ID');

            if (null === $tenantId) {
                throw new \InvalidArgumentException('Tenant ID is required');
            }
        }

        // Query customer by email
        $envelope = $this->queryBus->dispatch(new GetCustomerByEmailQuery($email, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new NotFoundHttpException('Customer profile not found');
        }

        $customerDTO = $handledStamp->getResult();

        if (null === $customerDTO) {
            throw new NotFoundHttpException('Customer profile not found');
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
