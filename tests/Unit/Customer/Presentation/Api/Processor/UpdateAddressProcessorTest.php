<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdateAddress\UpdateAddressCommand;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Presentation\Api\Processor\UpdateAddressProcessor;
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

#[CoversClass(UpdateAddressProcessor::class)]
final class UpdateAddressProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '55555555-5555-4555-8555-555555555555';
    private const ADDRESS_ID = '66666666-6666-4666-8666-666666666666';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private UpdateAddressProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new UpdateAddressProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesUpdateCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildAddressDTO();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateAddressCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

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
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->street, $result->street);
        self::assertSame($dto->type, $result->type);
        self::assertSame($dto->isDefaultShipping, $result->isDefaultShipping);
        self::assertSame($dto->isDefaultBilling, $result->isDefaultBilling);
    }

    #[Test]
    public function itMapsNullDatesWhenDtoHasNone(): void
    {
        $dto = $this->buildAddressDTO(createdAt: null, updatedAt: null);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertNull($result->createdAt);
        self::assertNull($result->updatedAt);
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
        $this->processor->process(
            new \stdClass(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    // ---------------------------------------------------------------
    // Validation guards – URI variables
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID and address ID are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['id' => self::ADDRESS_ID], []);
    }

    #[Test]
    public function itThrowsWhenAddressIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID and address ID are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new UpdateAddressProcessor($this->commandBus, $this->queryBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process(
            $this->buildValidResource(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
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
        $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    #[Test]
    public function itThrowsWhenTypeIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->type = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Street, city, postal code, country, and type are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    // ---------------------------------------------------------------
    // Handler failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itRethrowsHttpExceptionFromHandlerFailedException(): void
    {
        $httpException = new BadRequestHttpException('Address not found');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Address not found');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(
            $this->buildValidResource(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Persistence error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process(
            $this->buildValidResource(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(
            $this->buildValidResource(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Address not found after update');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(
            $this->buildValidResource(),
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): CustomerAddressResource
    {
        $resource = new CustomerAddressResource();
        $resource->street = '456 Elm St';
        $resource->city = 'Boston';
        $resource->postalCode = '02101';
        $resource->country = 'US';
        $resource->type = 'billing';
        $resource->isDefaultShipping = false;
        $resource->isDefaultBilling = true;

        return $resource;
    }

    private function buildAddressDTO(
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): CustomerAddressDTO {
        return new CustomerAddressDTO(
            id: self::ADDRESS_ID,
            customerId: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            street: '456 Elm St',
            street2: null,
            city: 'Boston',
            state: null,
            postalCode: '02101',
            country: 'US',
            type: 'billing',
            isDefaultShipping: false,
            isDefaultBilling: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
