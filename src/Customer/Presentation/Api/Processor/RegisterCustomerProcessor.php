<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RegisterCustomerCommand;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByIdQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

/**
 * @implements ProcessorInterface<CustomerEntity, CustomerEntity>
 */
final readonly class RegisterCustomerProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerEntity
    {
        if (!$data instanceof CustomerEntity) {
            throw new InvalidArgumentException('Expected CustomerEntity');
        }

        // Get tenant ID from context (injected by TenantContextProcessor decorator)
        if (!isset($context['tenant_id'])) {
            throw new RuntimeException('Tenant ID is required');
        }

        $tenantId = $context['tenant_id'];

        if (!$data->getEmail() || !$data->getFirstName() || !$data->getLastName()) {
            throw new InvalidArgumentException('Email, first name, and last name are required');
        }

        // Validate password is provided
        if (!$data->getPlainPassword()) {
            throw new InvalidArgumentException('Password is required for registration');
        }

        $customerId = CustomerId::generate()->toString();

        // Create User for authentication
        $this->createUserForCustomer(
            $data->getEmail(),
            $data->getPlainPassword(),
            $data->getFirstName() . ' ' . $data->getLastName()
        );

        $command = new RegisterCustomerCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            email: $data->getEmail(),
            firstName: $data->getFirstName(),
            lastName: $data->getLastName(),
            phoneNumber: $data->getPhoneNumber()
        );

        $this->commandBus->dispatch($command);

        // Retrieve the created customer
        $envelope = $this->queryBus->dispatch(new GetCustomerByIdQuery($customerId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new RuntimeException('No handler found for query');
        }

        $customerDTO = $handledStamp->getResult();

        if ($customerDTO === null) {
            throw new RuntimeException('Customer not found after registration');
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

    /**
     * Create a User entity for authentication when a customer registers
     */
    private function createUserForCustomer(string $email, string $plainPassword, string $username): void
    {
        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            // User already exists, skip creation (customer might be re-registering)
            return;
        }

        $user = new UserEntity();
        $user->setId((string) new Ulid());
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setRoles(['ROLE_CUSTOMER']); // Assign customer role
        $user->setCreatedAt(new \DateTimeImmutable());

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
