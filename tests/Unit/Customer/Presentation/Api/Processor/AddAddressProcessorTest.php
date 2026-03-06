<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\AddAddress\AddAddressCommand;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Presentation\Api\Processor\AddAddressProcessor;
use App\Customer\Presentation\Api\Resource\CustomerAddressResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(AddAddressProcessor::class)]
final class AddAddressProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '33333333-3333-4333-8333-333333333333';
    private const ADDRESS_ID = '44444444-4444-4444-8444-444444444444';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private AddAddressProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new AddAddressProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesAddCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildAddressDTO();

        $commandEnvelope = new Envelope(new \stdClass(), [new HandledStamp(self::ADDRESS_ID, 'handler')]);
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AddAddressCommand::class))
            ->willReturn($commandEnvelope);

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetAddressById::class))
            ->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->customerId, $result->customerId);
        self::assertSame($dto->street, $result->street);
        self::assertSame($dto->city, $result->city);
        self::assertSame($dto->country, $result->country);
        self::assertSame($dto->type, $result->type);
    }

    #[Test]
    public function itMapsOptionalFieldsFromDto(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01');
        $updatedAt = new \DateTimeImmutable('2024-06-01');
        $dto = $this->buildAddressDTO(
            street2: 'Apt 4B',
            state: 'NY',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $commandEnvelope = new Envelope(new \stdClass(), [new HandledStamp(self::ADDRESS_ID, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($commandEnvelope);

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $data->street2 = 'Apt 4B';
        $data->state = 'NY';

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertSame('Apt 4B', $result->street2);
        self::assertSame('NY', $result->state);
        self::assertSame($createdAt->format('c'), $result->createdAt);
        self::assertSame($updatedAt->format('c'), $result->updatedAt);
    }

    // ---------------------------------------------------------------
    // Validation guards – data type
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotCustomerAddressResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected CustomerAddressResource');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – URI variables
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new AddAddressProcessor($this->commandBus, $this->queryBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – required fields
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenStreetIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->street = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCityIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->city = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenPostalCodeIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->postalCode = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCountryIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->country = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenTypeIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->type = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Handler failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itRethrowsHttpExceptionFromHandlerFailedException(): void
    {
        $httpException = new BadRequestHttpException('Invalid address data');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid address data');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Domain error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCommandHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for command');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCommandHandlerResultIsNotString(): void
    {
        $commandEnvelope = new Envelope(new \stdClass(), [new HandledStamp(42, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($commandEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command handler did not return address ID');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $commandEnvelope = new Envelope(new \stdClass(), [new HandledStamp(self::ADDRESS_ID, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($commandEnvelope);
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $commandEnvelope = new Envelope(new \stdClass(), [new HandledStamp(self::ADDRESS_ID, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($commandEnvelope);

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Address not found after creation');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): CustomerAddressResource
    {
        $resource = new CustomerAddressResource();
        $resource->street = '123 Main St';
        $resource->city = 'New York';
        $resource->postalCode = '10001';
        $resource->country = 'US';
        $resource->type = 'shipping';

        return $resource;
    }

    private function buildAddressDTO(
        ?string $street2 = null,
        ?string $state = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): CustomerAddressDTO {
        return new CustomerAddressDTO(
            id: self::ADDRESS_ID,
            customerId: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            street: '123 Main St',
            street2: $street2,
            city: 'New York',
            state: $state,
            postalCode: '10001',
            country: 'US',
            type: 'shipping',
            isDefaultShipping: false,
            isDefaultBilling: false,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
