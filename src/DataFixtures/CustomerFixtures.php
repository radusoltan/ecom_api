<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Application\Command\DeactivateCustomerCommand;
use App\Customer\Application\Command\RegisterCustomerCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Customer fixtures - creates diverse customers for testing.
 */
class CustomerFixtures extends Fixture implements DependentFixtureInterface
{
    private const DEFAULT_PASSWORD = 'customer123';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "👥 Creating customers...\n";
        echo '   ℹ️  Default password for all test customers: '.self::DEFAULT_PASSWORD."\n";

        // Get first tenant ID from database
        $tenantIdString = $this->getFirstTenantId($manager);
        $tenantId = TenantId::fromString($tenantIdString);

        // Regular Customers (8)
        $this->createCustomer($tenantId, 'john.doe@example.com', 'John', 'Doe', '+14155551234', 'regular', true);
        $this->createCustomer($tenantId, 'jane.smith@example.com', 'Jane', 'Smith', '+14155555678', 'regular', true);
        $this->createCustomer($tenantId, 'mike.johnson@example.com', 'Mike', 'Johnson', null, 'regular', true);
        $this->createCustomer($tenantId, 'emily.wilson@example.com', 'Emily', 'Wilson', '+442071234567', 'regular', true);
        $this->createCustomer($tenantId, 'david.brown@example.com', 'David', 'Brown', '+33123456789', 'regular', true);
        $this->createCustomer($tenantId, 'sarah.davis@example.com', 'Sarah', 'Davis', '+491234567890', 'regular', false);
        $this->createCustomer($tenantId, 'robert.miller@example.com', 'Robert', 'Miller', '+14165559999', 'regular', true);
        $this->createCustomer($tenantId, 'lisa.anderson@example.com', 'Lisa', 'Anderson', null, 'regular', false);

        // VIP Customers (5)
        $this->createCustomer($tenantId, 'william.garcia@example.com', 'William', 'Garcia', '+14155551111', 'vip', true);
        $this->createCustomer($tenantId, 'maria.rodriguez@example.com', 'Maria', 'Rodriguez', '+34123456789', 'vip', true);
        $this->createCustomer($tenantId, 'james.martinez@example.com', 'James', 'Martinez', '+14085552222', 'vip', true);
        $this->createCustomer($tenantId, 'patricia.lee@example.com', 'Patricia', 'Lee', null, 'vip', true);
        $this->createCustomer($tenantId, 'michael.taylor@example.com', 'Michael', 'Taylor', '+14155553333', 'vip', false);

        // Premium Customers (2)
        $this->createCustomer($tenantId, 'christopher.thomas@example.com', 'Christopher', 'Thomas', '+14155554444', 'premium', true);
        $this->createCustomer($tenantId, 'jennifer.moore@example.com', 'Jennifer', 'Moore', '+442071235555', 'premium', true);

        $manager->flush();

        echo "   ✓ Created 15 customers (8 regular, 5 VIP, 2 premium)\n";
        echo "✅ Customer fixtures completed\n";
    }

    private function createCustomer(
        TenantId $tenantId,
        string $email,
        string $firstName,
        string $lastName,
        ?string $phoneNumber,
        string $segment,
        bool $isActive,
    ): void {
        $customerId = CustomerId::generate();

        // Create User for authentication (before customer)
        $this->createUserForCustomer($email, $firstName, $lastName);

        // Register customer
        $registerCommand = new RegisterCustomerCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            phoneNumber: $phoneNumber
        );

        $this->commandBus->dispatch($registerCommand);

        // Change segment if not regular
        if ('regular' !== $segment) {
            $changeSegmentCommand = new ChangeSegmentCommand(
                customerId: $customerId->toString(),
                tenantId: $tenantId->toString(),
                newSegment: $segment
            );

            $this->commandBus->dispatch($changeSegmentCommand);
        }

        // Deactivate if needed
        if (!$isActive) {
            $deactivateCommand = new DeactivateCustomerCommand(
                customerId: $customerId->toString(),
                tenantId: $tenantId->toString()
            );

            $this->commandBus->dispatch($deactivateCommand);
        }
    }

    private function getFirstTenantId(ObjectManager $manager): string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        return $result;
    }

    /**
     * Create a User entity for authentication when a customer is created via fixtures.
     */
    private function createUserForCustomer(string $email, string $firstName, string $lastName): void
    {
        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            // User already exists, skip creation
            return;
        }

        $user = new UserEntity();
        $user->setId((string) new Ulid());
        $user->setEmail($email);

        // Generate username from name
        $sanitized = strtolower(trim($firstName.' '.$lastName));
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
        $generatedUsername = sprintf('%s-%s', '' !== $sanitized ? trim($sanitized, '-') : 'customer', bin2hex(random_bytes(4)));
        $user->setUsername($generatedUsername);
        $user->setRoles(['ROLE_CUSTOMER']); // Assign customer role
        $user->setCreatedAt(new \DateTimeImmutable());

        // Hash the default password
        $hashedPassword = $this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }

    public function getOrder(): int
    {
        return 5;
    }
}
