<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Application\Query\GetAddressById\GetAddressByIdHandler;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAddressByIdHandler::class)]
final class GetAddressByIdHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    /** @var EntityRepository<CustomerAddressEntity> */
    private EntityRepository $addressRepository;
    private GetAddressByIdHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->addressRepository = $this->createMock(EntityRepository::class);
        $this->handler = new GetAddressByIdHandler($this->entityManager);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();

        $this->entityManager
            ->method('getRepository')
            ->with(CustomerAddressEntity::class)
            ->willReturn($this->addressRepository);
    }

    // ---------------------------------------------------------------
    // Happy path — address found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenAddressNotFound(): void
    {
        $this->addressRepository
            ->expects(self::once())
            ->method('find')
            ->with('some-address-id')
            ->willReturn(null);

        $query = new GetAddressById(
            addressId: 'some-address-id',
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsDtoWhenAddressFound(): void
    {
        $address = $this->buildAddressEntity(
            id: 'addr-001',
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            deleted: false,
        );

        $this->addressRepository
            ->expects(self::once())
            ->method('find')
            ->willReturn($address);

        $query = new GetAddressById(
            addressId: 'addr-001',
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerAddressDTO::class, $result);
        self::assertSame('addr-001', $result->id);
    }

    // ---------------------------------------------------------------
    // Deleted address
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullForDeletedAddress(): void
    {
        $address = $this->buildAddressEntity(
            id: 'addr-deleted',
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            deleted: true,
        );

        $this->addressRepository->method('find')->willReturn($address);

        $query = new GetAddressById(
            addressId: 'addr-deleted',
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // Ownership checks
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenAddressDoesNotBelongToCustomer(): void
    {
        $address = $this->buildAddressEntity(
            id: 'addr-002',
            customerId: CustomerId::generate()->toString(),
            tenantId: $this->tenantId->toString(),
            deleted: false,
        );

        $this->addressRepository->method('find')->willReturn($address);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Address does not belong to this customer');

        $query = new GetAddressById(
            addressId: 'addr-002',
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        ($this->handler)($query);
    }

    #[Test]
    public function itThrowsWhenAddressDoesNotBelongToTenant(): void
    {
        $otherTenant = TenantId::fromString('00000000-0000-4000-8000-000000000002');

        $address = $this->buildAddressEntity(
            id: 'addr-003',
            customerId: $this->customerId->toString(),
            tenantId: $otherTenant->toString(),
            deleted: false,
        );

        $this->addressRepository->method('find')->willReturn($address);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Address does not belong to this tenant');

        $query = new GetAddressById(
            addressId: 'addr-003',
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        ($this->handler)($query);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildAddressEntity(
        string $id,
        string $customerId,
        string $tenantId,
        bool $deleted,
    ): CustomerAddressEntity {
        $entity = $this->createMock(CustomerAddressEntity::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('getCustomerId')->willReturn($customerId);
        $entity->method('getTenantId')->willReturn($tenantId);
        $entity->method('isDeleted')->willReturn($deleted);
        $entity->method('getStreet')->willReturn('123 Main St');
        $entity->method('getStreet2')->willReturn(null);
        $entity->method('getCity')->willReturn('Berlin');
        $entity->method('getState')->willReturn(null);
        $entity->method('getPostalCode')->willReturn('10115');
        $entity->method('getCountry')->willReturn('DE');
        $entity->method('getType')->willReturn('shipping');
        $entity->method('isDefaultShipping')->willReturn(false);
        $entity->method('isDefaultBilling')->willReturn(false);
        $entity->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $entity->method('getUpdatedAt')->willReturn(new \DateTimeImmutable());

        return $entity;
    }
}
