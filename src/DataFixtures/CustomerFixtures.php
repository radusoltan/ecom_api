<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Customer\Application\Command\AddAddress\AddAddressCommand;
use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Application\Command\DeactivateCustomerCommand;
use App\Customer\Application\Command\RegisterCustomerCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Customer fixtures - creates diverse customers for testing.
 */
class CustomerFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-customers'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "👥 Creating customers...\n";
        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $faker = $this->faker('customers-'.$tenant['alias']);
            $this->activateTenantContext($tenantId);

            $createdForTenant = 0;

            for ($index = 1; $index <= Sprint3SeedData::CUSTOMERS_PER_TENANT; ++$index) {
                $firstName = $faker->firstName();
                $lastName = $faker->lastName();
                $customerId = CustomerId::generate();
                $phoneNumber = 0 === $index % 4 ? null : sprintf('+1%010d', 1000000000 + $index + (abs(crc32($tenant['alias'])) % 899999999));
                $segment = match (true) {
                    0 === $index % 11 => 'premium',
                    0 === $index % 4 => 'vip',
                    default => 'regular',
                };
                $isActive = 0 !== $index % 13;
                $email = sprintf(
                    '%s.%s.%03d@%s',
                    strtolower(preg_replace('/[^a-z]/i', '', $firstName) ?: 'shopper'),
                    strtolower(preg_replace('/[^a-z]/i', '', $lastName) ?: 'customer'),
                    $index,
                    $tenant['customerDomain']
                );

                $this->commandBus->dispatch(new RegisterCustomerCommand(
                    customerId: $customerId->toString(),
                    tenantId: $tenantId->toString(),
                    email: $email,
                    firstName: $firstName,
                    lastName: $lastName,
                    phoneNumber: $phoneNumber
                ));

                $primaryStreet2 = 0 === $index % 5 ? 'Suite '.(200 + $index) : null;
                $this->commandBus->dispatch(new AddAddressCommand(
                    customerId: $customerId->toString(),
                    tenantId: $tenantId->toString(),
                    street: $faker->streetAddress(),
                    street2: $primaryStreet2,
                    city: $faker->city(),
                    state: $this->usStateCode($faker),
                    postalCode: $faker->postcode(),
                    country: 'US',
                    type: 'both',
                    isDefaultShipping: true,
                    isDefaultBilling: true
                ));

                if (0 === $index % 3) {
                    $this->commandBus->dispatch(new AddAddressCommand(
                        customerId: $customerId->toString(),
                        tenantId: $tenantId->toString(),
                        street: $faker->streetAddress(),
                        street2: null,
                        city: $faker->city(),
                        state: $this->usStateCode($faker),
                        postalCode: $faker->postcode(),
                        country: 'US',
                        type: 'shipping',
                        isDefaultShipping: false,
                        isDefaultBilling: false
                    ));
                }

                if (0 === $index % 5) {
                    $this->commandBus->dispatch(new AddAddressCommand(
                        customerId: $customerId->toString(),
                        tenantId: $tenantId->toString(),
                        street: $faker->streetAddress(),
                        street2: 'Accounts Payable',
                        city: $faker->city(),
                        state: $this->usStateCode($faker),
                        postalCode: $faker->postcode(),
                        country: 'US',
                        type: 'billing',
                        isDefaultShipping: false,
                        isDefaultBilling: false
                    ));
                }

                if ('regular' !== $segment) {
                    $this->commandBus->dispatch(new ChangeSegmentCommand(
                        customerId: $customerId->toString(),
                        tenantId: $tenantId->toString(),
                        newSegment: $segment
                    ));
                }

                if (!$isActive) {
                    $this->commandBus->dispatch(new DeactivateCustomerCommand(
                        customerId: $customerId->toString(),
                        tenantId: $tenantId->toString()
                    ));
                }

                ++$createdForTenant;
            }

            echo sprintf("   ✓ %s customers: %d\n", $tenant['name'], $createdForTenant);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Customer population created\n";
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }
}
