<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByIdQuery;
use App\Customer\Application\Query\GetCustomerByIdQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCustomerByIdQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private GetCustomerByIdQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetCustomerByIdQueryHandler($this->customerRepository);
    }

    public function testHandleReturnsCustomerDTOWhenFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe',
            '+12345678901'
        );

        $query = new GetCustomerByIdQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::isInstanceOf(CustomerId::class), self::isInstanceOf(TenantId::class))
            ->willReturn($customer);

        $result = $this->handler->__invoke($query);

        self::assertInstanceOf(CustomerDTO::class, $result);
        self::assertEquals($customerId->toString(), $result->id);
        self::assertEquals($tenantId->toString(), $result->tenantId);
        self::assertEquals('john.doe@example.com', $result->email);
        self::assertEquals('John', $result->firstName);
        self::assertEquals('Doe', $result->lastName);
        self::assertEquals('John Doe', $result->fullName);
        self::assertEquals('+12345678901', $result->phoneNumber);
        self::assertEquals('regular', $result->segment);
        self::assertEquals(0, $result->loyaltyPoints);
        self::assertTrue($result->isActive);
    }

    public function testHandleReturnsNullWhenCustomerNotFound(): void
    {
        $query = new GetCustomerByIdQuery(
            customerId: CustomerId::generate()->toString(),
            tenantId: TenantId::generate()->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $result = $this->handler->__invoke($query);

        self::assertNull($result);
    }
}
