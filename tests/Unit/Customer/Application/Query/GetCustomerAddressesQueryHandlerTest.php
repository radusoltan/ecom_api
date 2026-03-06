<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetCustomerAddresses\GetCustomerAddressesQuery;
use App\Customer\Application\Query\GetCustomerAddresses\GetCustomerAddressesQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerAddressesQueryHandler::class)]
final class GetCustomerAddressesQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private GetCustomerAddressesQueryHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetCustomerAddressesQueryHandler($this->repository);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();

        $this->customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            Email::fromString('address-test@example.com'),
            'Eva',
            'Green'
        );
        $this->customer->pullDomainEvents();
    }

    private function addAddress(
        AddressType $type,
        bool $isDefaultShipping = false,
        bool $isDefaultBilling = false,
    ): CustomerAddressId {
        $addressId = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $addressId,
            street: '1 Sample St',
            street2: null,
            city: 'Berlin',
            state: null,
            postalCode: '10115',
            country: 'DE',
            type: $type,
            isDefaultShipping: $isDefaultShipping,
            isDefaultBilling: $isDefaultBilling
        );
        $this->customer->addAddress($address);
        $this->customer->pullDomainEvents();

        return $addressId;
    }

    // ---------------------------------------------------------------
    // Happy path — no filter
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsAllAddressesWithoutFilter(): void
    {
        $this->addAddress(AddressType::SHIPPING);
        $this->addAddress(AddressType::BILLING);
        $this->addAddress(AddressType::BOTH);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString()
        );

        $result = ($this->handler)($query);

        self::assertCount(3, $result);
        self::assertContainsOnlyInstancesOf(CustomerAddressDTO::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenCustomerHasNoAddresses(): void
    {
        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString()
        );

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // Filtering by type
    // ---------------------------------------------------------------

    #[Test]
    public function itFiltersShippingAddresses(): void
    {
        $this->addAddress(AddressType::SHIPPING);  // included
        $this->addAddress(AddressType::BILLING);   // excluded
        $this->addAddress(AddressType::BOTH);       // included (isShipping = true)

        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            type: 'shipping'
        );

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        foreach ($result as $dto) {
            self::assertThat(
                $dto->type,
                self::logicalOr(
                    self::equalTo('shipping'),
                    self::equalTo('both')
                )
            );
        }
    }

    #[Test]
    public function itFiltersBillingAddresses(): void
    {
        $this->addAddress(AddressType::SHIPPING);  // excluded
        $this->addAddress(AddressType::BILLING);   // included
        $this->addAddress(AddressType::BOTH);       // included (isBilling = true)

        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            type: 'billing'
        );

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        foreach ($result as $dto) {
            self::assertThat(
                $dto->type,
                self::logicalOr(
                    self::equalTo('billing'),
                    self::equalTo('both')
                )
            );
        }
    }

    #[Test]
    public function itFiltersBothTypeAddresses(): void
    {
        $this->addAddress(AddressType::SHIPPING);  // excluded
        $this->addAddress(AddressType::BILLING);   // excluded
        $this->addAddress(AddressType::BOTH);       // included
        $this->addAddress(AddressType::BOTH);       // included

        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            type: 'both'
        );

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        foreach ($result as $dto) {
            self::assertSame('both', $dto->type);
        }
    }

    // ---------------------------------------------------------------
    // DTO field mapping
    // ---------------------------------------------------------------

    #[Test]
    public function itMapsDtoFieldsFromAddress(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $addressId,
            street: '99 Elm Ave',
            street2: 'Suite 5',
            city: 'Hamburg',
            state: 'HH',
            postalCode: '20095',
            country: 'DE',
            type: AddressType::BILLING,
            isDefaultShipping: false,
            isDefaultBilling: true
        );
        $this->customer->addAddress($address);
        $this->customer->pullDomainEvents();

        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString()
        );

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        $dto = $result[0];
        self::assertSame($addressId->toString(), $dto->id);
        self::assertSame($this->customerId->toString(), $dto->customerId);
        self::assertSame($this->tenantId->toString(), $dto->tenantId);
        self::assertSame('99 Elm Ave', $dto->street);
        self::assertSame('Suite 5', $dto->street2);
        self::assertSame('Hamburg', $dto->city);
        self::assertSame('HH', $dto->state);
        self::assertSame('20095', $dto->postalCode);
        self::assertSame('DE', $dto->country);
        self::assertSame('billing', $dto->type);
        self::assertFalse($dto->isDefaultShipping);
        self::assertTrue($dto->isDefaultBilling);
    }

    // ---------------------------------------------------------------
    // Error path
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        $query = new GetCustomerAddressesQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString()
        );

        ($this->handler)($query);
    }
}
