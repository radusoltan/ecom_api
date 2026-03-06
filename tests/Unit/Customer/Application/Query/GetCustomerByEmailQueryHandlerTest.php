<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByEmailQuery;
use App\Customer\Application\Query\GetCustomerByEmailQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerByEmailQueryHandler::class)]
final class GetCustomerByEmailQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private GetCustomerByEmailQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetCustomerByEmailQueryHandler($this->repository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsCustomerDtoWhenFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $email = 'alice@example.com';

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString($email),
            'Alice',
            'Wonder',
            '+12025550100'
        );

        $this->repository
            ->expects(self::once())
            ->method('findByEmail')
            ->with(
                self::isInstanceOf(Email::class),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($customer);

        $query = new GetCustomerByEmailQuery(
            email: $email,
            tenantId: $tenantId->toString()
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerDTO::class, $result);
        self::assertSame($customerId->toString(), $result->id);
        self::assertSame($email, $result->email);
        self::assertSame('Alice', $result->firstName);
        self::assertSame('Wonder', $result->lastName);
        self::assertSame('Alice Wonder', $result->fullName);
        self::assertSame('+12025550100', $result->phoneNumber);
        self::assertSame('regular', $result->segment);
        self::assertSame(0, $result->loyaltyPoints);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itReturnsNullWhenCustomerNotFound(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $query = new GetCustomerByEmailQuery(
            email: 'notexist@example.com',
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001')->toString()
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itPassesEmailAsValueObjectToRepository(): void
    {
        $email = 'check@domain.com';
        $tenantId = TenantId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findByEmail')
            ->with(
                self::callback(fn (Email $e) => $e->toString() === $email),
                self::callback(fn (TenantId $t) => $t->toString() === $tenantId->toString())
            )
            ->willReturn(null);

        $query = new GetCustomerByEmailQuery(email: $email, tenantId: $tenantId->toString());

        ($this->handler)($query);
    }

    #[Test]
    public function itMapsAllDtoFieldsCorrectly(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('bob@example.com'),
            'Bob',
            'Builder'
        );

        $this->repository->method('findByEmail')->willReturn($customer);

        $query = new GetCustomerByEmailQuery(
            email: 'bob@example.com',
            tenantId: $tenantId->toString()
        );

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        self::assertSame($tenantId->toString(), $result->tenantId);
        self::assertNull($result->phoneNumber);
        self::assertSame(0, $result->loyaltyPoints);
        self::assertSame('regular', $result->segment);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function itHandlesDifferentTenantContexts(): void
    {
        $customerId = CustomerId::generate();
        $tenantId1 = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId1,
            Email::fromString('shared@example.com'),
            'Shared',
            'User'
        );

        $this->repository->method('findByEmail')->willReturn($customer);

        $query = new GetCustomerByEmailQuery(
            email: 'shared@example.com',
            tenantId: $tenantId1->toString()
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerDTO::class, $result);
        self::assertSame($tenantId1->toString(), $result->tenantId);
    }

    #[Test]
    public function itReturnsNullForUnknownEmailInExistingTenant(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $query = new GetCustomerByEmailQuery(
            email: 'ghost@example.com',
            tenantId: TenantId::generate()->toString()
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }
}
