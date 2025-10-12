<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetAllCustomersQuery;
use App\Customer\Application\Query\GetAllCustomersQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetAllCustomersQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private GetAllCustomersQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetAllCustomersQueryHandler($this->customerRepository);
    }

    public function testHandleReturnsAllCustomersWithoutFilter(): void
    {
        $tenantId = TenantId::generate();

        $customer1 = Customer::register(
            CustomerId::generate(),
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe'
        );

        $customer2 = Customer::register(
            CustomerId::generate(),
            $tenantId,
            Email::fromString('jane.smith@example.com'),
            'Jane',
            'Smith'
        );
        $customer2->changeSegment(CustomerSegment::vip());

        $query = new GetAllCustomersQuery(
            tenantId: $tenantId->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findAll')
            ->with(self::isInstanceOf(TenantId::class))
            ->willReturn([$customer1, $customer2]);

        $this->customerRepository
            ->expects(self::never())
            ->method('findBySegment');

        $result = $this->handler->__invoke($query);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(CustomerDTO::class, $result);
        self::assertEquals('John', $result[0]->firstName);
        self::assertEquals('Jane', $result[1]->firstName);
    }

    public function testHandleReturnsFilteredCustomersBySegment(): void
    {
        $tenantId = TenantId::generate();

        $vipCustomer = Customer::register(
            CustomerId::generate(),
            $tenantId,
            Email::fromString('vip@example.com'),
            'VIP',
            'Customer'
        );
        $vipCustomer->changeSegment(CustomerSegment::vip());

        $query = new GetAllCustomersQuery(
            tenantId: $tenantId->toString(),
            segment: 'vip'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findBySegment')
            ->with('vip', self::isInstanceOf(TenantId::class))
            ->willReturn([$vipCustomer]);

        $this->customerRepository
            ->expects(self::never())
            ->method('findAll');

        $result = $this->handler->__invoke($query);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertContainsOnlyInstancesOf(CustomerDTO::class, $result);
        self::assertEquals('vip', $result[0]->segment);
    }

    public function testHandleReturnsEmptyArrayWhenNoCustomersFound(): void
    {
        $query = new GetAllCustomersQuery(
            tenantId: TenantId::generate()->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->handler->__invoke($query);

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }
}
