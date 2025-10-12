<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Fixtures;

use App\Customer\Application\Command\ActivateCustomerCommand;
use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Application\Command\DeactivateCustomerCommand;
use App\Customer\Application\Command\RegisterCustomerCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Customer fixtures for development and testing
 *
 * Creates 15 diverse customers across all segments:
 * - 8 Regular customers (53%)
 * - 5 VIP customers (33%)
 * - 2 Premium customers (13%)
 *
 * Distribution includes:
 * - Active and inactive customers
 * - Customers with and without phone numbers
 * - International phone formats
 * - Variety of names (including edge cases)
 */
final class B_CustomerFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Get first tenant ID from database
        $tenantIdString = $this->getFirstTenantId($manager);

        if (!$tenantIdString) {
            echo "⚠️  No tenants found in database. Skipping customer fixtures.\n";
            return;
        }

        $tenantId = TenantId::fromString($tenantIdString);

        echo "👥 Creating customers for tenant: {$tenantIdString}\n";

        // Regular Customers (8)
        $this->createCustomer(
            $tenantId,
            'john.doe@example.com',
            'John',
            'Doe',
            '+14155551234',
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'jane.smith@example.com',
            'Jane',
            'Smith',
            '+14155555678',
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'mike.johnson@example.com',
            'Mike',
            'Johnson',
            null, // No phone
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'emily.wilson@example.com',
            'Emily',
            'Wilson',
            '+442071234567', // UK format
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'david.brown@example.com',
            'David',
            'Brown',
            '+33123456789', // France format
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'sarah.davis@example.com',
            'Sarah',
            'Davis',
            '+491234567890', // Germany format
            'regular',
            false // Inactive
        );

        $this->createCustomer(
            $tenantId,
            'robert.miller@example.com',
            'Robert',
            'Miller',
            '+14165559999',
            'regular',
            true
        );

        $this->createCustomer(
            $tenantId,
            'lisa.anderson@example.com',
            'Lisa',
            'Anderson',
            null,
            'regular',
            false // Inactive
        );

        // VIP Customers (5)
        $this->createCustomer(
            $tenantId,
            'william.garcia@example.com',
            'William',
            'Garcia',
            '+14155551111',
            'vip',
            true
        );

        $this->createCustomer(
            $tenantId,
            'maria.rodriguez@example.com',
            'Maria',
            'Rodriguez',
            '+34123456789', // Spain format
            'vip',
            true
        );

        $this->createCustomer(
            $tenantId,
            'james.martinez@example.com',
            'James',
            'Martinez',
            '+14085552222',
            'vip',
            true
        );

        $this->createCustomer(
            $tenantId,
            'patricia.lee@example.com',
            'Patricia',
            'Lee',
            null,
            'vip',
            true
        );

        $this->createCustomer(
            $tenantId,
            'michael.taylor@example.com',
            'Michael',
            'Taylor',
            '+14155553333',
            'vip',
            false // Inactive VIP
        );

        // Premium Customers (2)
        $this->createCustomer(
            $tenantId,
            'christopher.thomas@example.com',
            'Christopher',
            'Thomas',
            '+14155554444',
            'premium',
            true
        );

        $this->createCustomer(
            $tenantId,
            'jennifer.moore@example.com',
            'Jennifer',
            'Moore',
            '+442071235555', // UK format
            'premium',
            true
        );

        $manager->flush();

        echo "   ✓ Created 15 customers (8 regular, 5 VIP, 2 premium)\n";
    }

    private function getFirstTenantId(ObjectManager $manager): ?string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants LIMIT 1')->fetchOne();

        return $result ?: null;
    }

    public function getOrder(): int
    {
        // Run after TenantFixtures (order 1) and before other fixtures
        return 2;
    }

    private function createCustomer(
        TenantId $tenantId,
        string $email,
        string $firstName,
        string $lastName,
        ?string $phoneNumber,
        string $segment,
        bool $isActive
    ): void {
        $customerId = CustomerId::generate();

        // Register customer (always starts as 'regular' and 'active')
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
        if ($segment !== 'regular') {
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
}
