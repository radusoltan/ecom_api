<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\SetDefaultAddress\SetDefaultAddressCommand;
use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\Query\GetAddressById\GetAddressById;
use App\Customer\Presentation\Api\Processor\SetDefaultAddressProcessor;
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

#[CoversClass(SetDefaultAddressProcessor::class)]
final class SetDefaultAddressProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '77777777-7777-4777-8777-777777777777';
    private const ADDRESS_ID = '88888888-8888-4888-8888-888888888888';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private SetDefaultAddressProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new SetDefaultAddressProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path – type from URI template (set-default-shipping)
    // ---------------------------------------------------------------

    #[Test]
    public function itSetsDefaultShippingWhenUriTemplateEndsWithSetDefaultShipping(): void
    {
        $dto = $this->buildAddressDTO(isDefaultShipping: true);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SetDefaultAddressCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->with(self::isInstanceOf(GetAddressById::class))->willReturn($queryEnvelope);

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-shipping');

        $result = $this->processor->process(
            null,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
        self::assertTrue($result->isDefaultShipping);
    }

    #[Test]
    public function itSetsDefaultBillingWhenUriTemplateEndsWithSetDefaultBilling(): void
    {
        $dto = $this->buildAddressDTO(isDefaultBilling: true);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-billing');

        $result = $this->processor->process(
            null,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
        self::assertTrue($result->isDefaultBilling);
    }

    // ---------------------------------------------------------------
    // Happy path – type from data body (PATCH /addresses/{id}/default)
    // ---------------------------------------------------------------

    #[Test]
    public function itSetsDefaultTypeFromDataBodyWhenUriTemplateDoesNotMatch(): void
    {
        $dto = $this->buildAddressDTO(isDefaultShipping: true);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/default');

        $data = new CustomerAddressResource();
        $data->type = 'shipping';

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
    }

    #[Test]
    public function itSetsDefaultTypeFromDataBodyWithBilling(): void
    {
        $dto = $this->buildAddressDTO(isDefaultBilling: true);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/default');

        $data = new CustomerAddressResource();
        $data->type = 'billing';

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
    }

    // ---------------------------------------------------------------
    // Happy path – non-HttpOperation (uses data body)
    // ---------------------------------------------------------------

    #[Test]
    public function itUsesDataTypeWhenOperationIsNotHttpOperation(): void
    {
        $dto = $this->buildAddressDTO(isDefaultShipping: true);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(Operation::class);

        $data = new CustomerAddressResource();
        $data->type = 'shipping';

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );

        self::assertInstanceOf(CustomerAddressResource::class, $result);
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
        $this->processor->process(null, $operation, ['id' => self::ADDRESS_ID], []);
    }

    #[Test]
    public function itThrowsWhenAddressIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID and address ID are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new SetDefaultAddressProcessor($this->commandBus, $this->queryBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – type
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTypeCannotBeDetermined(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Type (shipping or billing) is required');

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/default');

        // data has no type
        $this->processor->process(
            null,
            $operation,
            ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID],
            []
        );
    }

    #[Test]
    public function itThrowsWhenTypeIsInvalid(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Type must be either "shipping" or "billing"');

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/default');

        $data = new CustomerAddressResource();
        $data->type = 'both'; // invalid for this processor

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

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-shipping');

        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Repo error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-shipping');

        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-shipping');

        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Address not found after update');

        $operation = $this->createMock(HttpOperation::class);
        $operation->method('getUriTemplate')->willReturn('/customers/{customerId}/addresses/{id}/set-default-shipping');

        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID, 'id' => self::ADDRESS_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildAddressDTO(bool $isDefaultShipping = false, bool $isDefaultBilling = false): CustomerAddressDTO
    {
        return new CustomerAddressDTO(
            id: self::ADDRESS_ID,
            customerId: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            street: '100 Oak Ave',
            street2: null,
            city: 'Chicago',
            state: 'IL',
            postalCode: '60601',
            country: 'US',
            type: 'shipping',
            isDefaultShipping: $isDefaultShipping,
            isDefaultBilling: $isDefaultBilling,
        );
    }
}
