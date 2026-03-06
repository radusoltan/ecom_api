<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Application\Query\GetCustomerConsents\GetCustomerConsentsQuery;
use App\Customer\Application\Query\GetCustomerConsents\GetCustomerConsentsQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerConsentsQueryHandler::class)]
final class GetCustomerConsentsQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private GetCustomerConsentsQueryHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetCustomerConsentsQueryHandler($this->repository);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();

        $this->customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            Email::fromString('consents-test@example.com'),
            'Ada',
            'Lovelace',
        );
        $this->customer->pullDomainEvents();
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsDtoWithDefaultConsents(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($this->customerId, $this->tenantId)
            ->willReturn($this->customer);

        $query = new GetCustomerConsentsQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerConsentsDTO::class, $result);
        self::assertSame($this->customerId->toString(), $result->customerId);
        // Default consents are all false (GDPR opt-in)
        self::assertFalse($result->marketingEmail);
        self::assertFalse($result->marketingSms);
        self::assertFalse($result->thirdPartySharing);
        self::assertFalse($result->analyticsTracking);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->lastUpdatedAt);
    }

    #[Test]
    public function itReflectsUpdatedConsents(): void
    {
        $this->customer->updateConsent(
            \App\Customer\Domain\ValueObject\ConsentType::MARKETING_EMAIL,
            true,
        );
        $this->customer->pullDomainEvents();

        $this->repository->method('findById')->willReturn($this->customer);

        $query = new GetCustomerConsentsQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertTrue($result->marketingEmail);
        self::assertFalse($result->marketingSms);
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

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        $query = new GetCustomerConsentsQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        ($this->handler)($query);
    }
}
