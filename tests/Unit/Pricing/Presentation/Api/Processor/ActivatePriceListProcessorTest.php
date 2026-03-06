<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Pricing\Application\Command\ActivatePriceList\ActivatePriceListCommand;
use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Presentation\Api\Processor\ActivatePriceListProcessor;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ActivatePriceListProcessor::class)]
final class ActivatePriceListProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PRICE_LIST_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private ActivatePriceListProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new ActivatePriceListProcessor($this->commandBus, $this->queryBus);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesActivateCommandAndReturnsPopulatedResource(): void
    {
        $dto = $this->buildPriceListDto(isActive: true);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ActivatePriceListCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $data = new PriceListResource();
        $data->tenantId = self::TENANT_ID;

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process(
            $data,
            $operation,
            ['id' => self::PRICE_LIST_ID]
        );

        self::assertInstanceOf(PriceListResource::class, $result);
        self::assertTrue($result->isActive);
        self::assertSame(self::PRICE_LIST_ID, $result->id);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotPriceListResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected PriceListResource');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['id' => self::PRICE_LIST_ID]);
    }

    #[Test]
    public function itThrowsWhenPriceListIdIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price list ID is required');

        $data = new PriceListResource();
        $data->tenantId = self::TENANT_ID;
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, []);
    }

    #[Test]
    public function itThrowsWhenTenantIdIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $data = new PriceListResource();
        // tenantId is null
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PRICE_LIST_ID]);
    }

    #[Test]
    public function itThrowsWhenQueryReturnsNullDto(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PriceList not found');

        $data = new PriceListResource();
        $data->tenantId = self::TENANT_ID;
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PRICE_LIST_ID]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildPriceListDto(bool $isActive = true): PriceListDTO
    {
        return new PriceListDTO(
            id: self::PRICE_LIST_ID,
            tenantId: self::TENANT_ID,
            name: 'Activation Test List',
            priority: 100,
            rules: [],
            validFrom: null,
            validTo: null,
            isActive: $isActive,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );
    }
}
