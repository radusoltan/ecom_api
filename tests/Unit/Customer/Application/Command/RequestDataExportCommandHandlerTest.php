<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RequestDataExport\RequestDataExportCommand;
use App\Customer\Application\Command\RequestDataExport\RequestDataExportCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestDataExportCommandHandlerTest extends TestCase
{
    private DataExportRequestRepositoryInterface $exportRepository;
    private CustomerRepositoryInterface $customerRepository;
    private MessageBusInterface $messageBus;
    private RequestDataExportCommandHandler $handler;

    protected function setUp(): void
    {
        $this->exportRepository = $this->createMock(DataExportRequestRepositoryInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new RequestDataExportCommandHandler(
            $this->exportRepository,
            $this->customerRepository,
            $this->messageBus,
            new NullLogger()
        );
    }

    public function testRequestSuccess(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        // Mock customer exists
        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'Test',
            lastName: 'Customer'
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->willReturn($customer);

        // No pending requests
        $this->exportRepository->expects($this->once())
            ->method('findPendingByCustomerId')
            ->willReturn(null);

        // No rate limit exceeded (0 recent requests)
        $this->exportRepository->expects($this->once())
            ->method('countRecentByCustomerId')
            ->willReturn(0);

        $this->exportRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (DataExportRequest $request) use ($customerId, $tenantId) {
                return $request->customerId()->equals($customerId)
                    && $request->tenantId()->equals($tenantId);
            }));

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = new RequestDataExportCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $requestId = ($this->handler)($command);

        self::assertIsString($requestId);
    }

    public function testRequestFailsWhenRateLimited(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        // Mock customer exists
        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'Test',
            lastName: 'Customer'
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->willReturn($customer);

        // No pending requests
        $this->exportRepository->expects($this->once())
            ->method('findPendingByCustomerId')
            ->willReturn(null);

        // Rate limited - recent request exists
        $this->exportRepository->expects($this->once())
            ->method('countRecentByCustomerId')
            ->willReturn(1);

        $this->exportRepository->expects($this->never())
            ->method('save');

        $command = new RequestDataExportCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        ($this->handler)($command);
    }

    public function testRequestFailsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $command = new RequestDataExportCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    public function testDispatchesAsyncMessage(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        // Mock customer exists
        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'Test',
            lastName: 'Customer'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->exportRepository->method('findPendingByCustomerId')->willReturn(null);
        $this->exportRepository->method('countRecentByCustomerId')->willReturn(0);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = new RequestDataExportCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        ($this->handler)($command);
    }
}
