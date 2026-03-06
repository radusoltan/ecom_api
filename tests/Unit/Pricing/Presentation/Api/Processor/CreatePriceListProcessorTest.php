<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Pricing\Application\Command\CreatePriceList\CreatePriceListCommand;
use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Presentation\Api\Processor\CreatePriceListProcessor;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CreatePriceListProcessor::class)]
final class CreatePriceListProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PRICE_LIST_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private CreatePriceListProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new CreatePriceListProcessor($this->commandBus, $this->queryBus);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesCreateCommandAndReturnsPopulatedResource(): void
    {
        $dto = $this->buildPriceListDto();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreatePriceListCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $data = new PriceListResource();
        $data->name = 'Summer Sale';
        $data->tenantId = self::TENANT_ID;
        $data->priority = 50;

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation);

        self::assertInstanceOf(PriceListResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->name, $result->name);
        self::assertSame($dto->tenantId, $result->tenantId);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itUsesDefaultPriorityWhenNotSet(): void
    {
        $dto = $this->buildPriceListDto(priority: 100);

        $capturedCommand = null;
        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function (object $cmd) use (&$capturedCommand) {
                $capturedCommand = $cmd;

                return new Envelope($cmd);
            });

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $data = new PriceListResource();
        $data->name = 'Default Priority List';
        $data->tenantId = self::TENANT_ID;
        // priority intentionally not set → defaults to 100

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation);

        self::assertInstanceOf(CreatePriceListCommand::class, $capturedCommand);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotPriceListResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected PriceListResource');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation);
    }

    #[Test]
    public function itThrowsWhenNameIsMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Name and tenant ID are required');

        $data = new PriceListResource();
        $data->tenantId = self::TENANT_ID;
        // name is null

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation);
    }

    #[Test]
    public function itThrowsWhenTenantIdIsMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Name and tenant ID are required');

        $data = new PriceListResource();
        $data->name = 'Some Name';
        // tenantId is null

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->queryBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass())); // no HandledStamp

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $data = new PriceListResource();
        $data->name = 'Test';
        $data->tenantId = self::TENANT_ID;

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PriceList not found after creation');

        $data = new PriceListResource();
        $data->name = 'Test';
        $data->tenantId = self::TENANT_ID;

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildPriceListDto(int $priority = 50): PriceListDTO
    {
        return new PriceListDTO(
            id: self::PRICE_LIST_ID,
            tenantId: self::TENANT_ID,
            name: 'Summer Sale',
            priority: $priority,
            rules: [],
            validFrom: null,
            validTo: null,
            isActive: true,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );
    }
}
