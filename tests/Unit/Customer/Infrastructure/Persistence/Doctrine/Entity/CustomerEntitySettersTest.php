<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerEntity::class)]
#[Group('customer')]
final class CustomerEntitySettersTest extends TestCase
{
    private Customer $customer;
    private CustomerEntity $entity;

    protected function setUp(): void
    {
        $this->customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('test@example.com'),
            'John',
            'Doe',
            '+12345678901',
        );

        $this->entity = CustomerEntity::fromDomainModel($this->customer);
    }

    // ---------------------------------------------------------------
    // Setters
    // ---------------------------------------------------------------

    #[Test]
    public function itSetsIdViaSetterMethod(): void
    {
        $this->entity->setId('new-id-value');

        self::assertSame('new-id-value', $this->entity->getId());
    }

    #[Test]
    public function itSetsTenantIdViaSetterMethod(): void
    {
        $this->entity->setTenantId('new-tenant-id');

        self::assertSame('new-tenant-id', $this->entity->getTenantId());
    }

    #[Test]
    public function itSetsEmailViaSetterMethod(): void
    {
        $this->entity->setEmail('new@example.com');

        self::assertSame('new@example.com', $this->entity->getEmail());
    }

    #[Test]
    public function itSetsFirstNameViaSetterMethod(): void
    {
        $this->entity->setFirstName('Jane');

        self::assertSame('Jane', $this->entity->getFirstName());
    }

    #[Test]
    public function itSetsLastNameViaSetterMethod(): void
    {
        $this->entity->setLastName('Smith');

        self::assertSame('Smith', $this->entity->getLastName());
    }

    #[Test]
    public function itSetsPhoneNumberViaSetterMethod(): void
    {
        $this->entity->setPhoneNumber('+19876543210');

        self::assertSame('+19876543210', $this->entity->getPhoneNumber());
    }

    #[Test]
    public function itSetsPhoneNumberToNullViaSetterMethod(): void
    {
        $this->entity->setPhoneNumber(null);

        self::assertNull($this->entity->getPhoneNumber());
    }

    #[Test]
    public function itSetsSegmentViaSetterMethod(): void
    {
        $this->entity->setSegment('vip');

        self::assertSame('vip', $this->entity->getSegment());
    }

    #[Test]
    public function itSetsLoyaltyPointsViaSetterMethod(): void
    {
        $this->entity->setLoyaltyPoints(500);

        self::assertSame(500, $this->entity->getLoyaltyPoints());
    }

    #[Test]
    public function itSetsIsActiveViaSetterMethod(): void
    {
        $this->entity->setIsActive(false);

        self::assertFalse($this->entity->isActive());
    }

    #[Test]
    public function itSetsCreatedAtViaSetterMethod(): void
    {
        $date = new \DateTimeImmutable('2024-01-15 10:00:00');
        $this->entity->setCreatedAt($date);

        self::assertSame($date, $this->entity->getCreatedAt());
    }

    #[Test]
    public function itSetsUpdatedAtViaSetterMethod(): void
    {
        $date = new \DateTimeImmutable('2024-06-15 12:00:00');
        $this->entity->setUpdatedAt($date);

        self::assertSame($date, $this->entity->getUpdatedAt());
    }

    #[Test]
    public function itSetsPreferencesViaSetterMethod(): void
    {
        $this->entity->setPreferences('{"key":"value"}');

        self::assertSame('{"key":"value"}', $this->entity->getPreferences());
    }

    // ---------------------------------------------------------------
    // Plain password (transient field)
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullForPlainPasswordByDefault(): void
    {
        self::assertNull($this->entity->getPlainPassword());
    }

    #[Test]
    public function itSetsAndGetsPlainPassword(): void
    {
        $this->entity->setPlainPassword('secret123');

        self::assertSame('secret123', $this->entity->getPlainPassword());
    }

    #[Test]
    public function itSetsPlainPasswordToNull(): void
    {
        $this->entity->setPlainPassword('secret123');
        $this->entity->setPlainPassword(null);

        self::assertNull($this->entity->getPlainPassword());
    }

    // ---------------------------------------------------------------
    // Email blind index
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullForEmailBlindIndexByDefault(): void
    {
        self::assertNull($this->entity->getEmailBlindIndex());
    }

    #[Test]
    public function itSetsAndGetsEmailBlindIndex(): void
    {
        $blindIndex = str_repeat('a', 64);
        $this->entity->setEmailBlindIndex($blindIndex);

        self::assertSame($blindIndex, $this->entity->getEmailBlindIndex());
    }

    #[Test]
    public function itSetsEmailBlindIndexToNull(): void
    {
        $this->entity->setEmailBlindIndex('some-index');
        $this->entity->setEmailBlindIndex(null);

        self::assertNull($this->entity->getEmailBlindIndex());
    }

    // ---------------------------------------------------------------
    // Computed / derived methods
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFullNameCombiningFirstAndLastName(): void
    {
        self::assertSame('John Doe', $this->entity->getFullName());
    }

    #[Test]
    public function itReturnsUpdatedFullNameAfterSetters(): void
    {
        $this->entity->setFirstName('Jane');
        $this->entity->setLastName('Smith');

        self::assertSame('Jane Smith', $this->entity->getFullName());
    }

    // ---------------------------------------------------------------
    // HATEOAS links
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsLinksArrayWithExpectedKeys(): void
    {
        $links = $this->entity->getLinks();

        self::assertArrayHasKey('self', $links);
        self::assertArrayHasKey('orders', $links);
        self::assertArrayHasKey('addresses', $links);
        self::assertArrayHasKey('preferences', $links);
    }

    #[Test]
    public function itReturnsLinksContainingTheCustomerId(): void
    {
        $id = $this->entity->getId();
        $links = $this->entity->getLinks();

        self::assertStringContainsString($id, $links['self']['href']);
        self::assertStringContainsString($id, $links['orders']['href']);
        self::assertStringContainsString($id, $links['addresses']['href']);
        self::assertStringContainsString($id, $links['preferences']['href']);
    }

    // ---------------------------------------------------------------
    // Address collection management
    // ---------------------------------------------------------------

    #[Test]
    public function itStartsWithEmptyAddressEntityCollection(): void
    {
        self::assertCount(0, $this->entity->getAddressEntities());
    }

    #[Test]
    public function itAddsAddressEntityToCollection(): void
    {
        $addressEntity = $this->createMock(CustomerAddressEntity::class);
        $addressEntity->method('getId')->willReturn('addr-001');

        $this->entity->addAddressEntity($addressEntity);

        self::assertCount(1, $this->entity->getAddressEntities());
    }

    #[Test]
    public function itDoesNotAddDuplicateAddressEntity(): void
    {
        $addressEntity = $this->createMock(CustomerAddressEntity::class);
        $addressEntity->method('getId')->willReturn('addr-001');

        $this->entity->addAddressEntity($addressEntity);
        $this->entity->addAddressEntity($addressEntity);

        self::assertCount(1, $this->entity->getAddressEntities());
    }

    #[Test]
    public function itRemovesAddressEntityFromCollection(): void
    {
        $addressEntity = $this->createMock(CustomerAddressEntity::class);
        $addressEntity->method('getId')->willReturn('addr-001');

        $this->entity->addAddressEntity($addressEntity);
        $this->entity->removeAddressEntity($addressEntity);

        self::assertCount(0, $this->entity->getAddressEntities());
    }

    #[Test]
    public function itHandlesRemovingNonExistentAddressEntityGracefully(): void
    {
        $addressEntity = $this->createMock(CustomerAddressEntity::class);
        $addressEntity->method('getId')->willReturn('addr-999');

        // Should not throw
        $this->entity->removeAddressEntity($addressEntity);

        self::assertCount(0, $this->entity->getAddressEntities());
    }
}
