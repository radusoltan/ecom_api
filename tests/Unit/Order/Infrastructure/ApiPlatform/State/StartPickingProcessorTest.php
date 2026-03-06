<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use App\Order\Infrastructure\ApiPlatform\State\StartPickingProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(StartPickingProcessor::class)]
final class StartPickingProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private StartPickingProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new StartPickingProcessor($this->commandBus);
    }

    #[Test]
    public function processDispatchesCommandAndReturnsData(): void
    {
        $data = new \stdClass();
        $operation = $this->createMock(Operation::class);

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process(
            $data,
            $operation,
            ['id' => '01JQKM0000GGGG000000000001'],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        self::assertSame($data, $result);
    }

    #[Test]
    public function processThrowsWhenNoFulfillmentId(): void
    {
        $operation = $this->createMock(Operation::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Fulfillment ID is required');

        $this->processor->process(
            new \stdClass(),
            $operation,
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    #[Test]
    public function processThrowsWhenNoTenantId(): void
    {
        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        $this->processor->process(
            new \stdClass(),
            $operation,
            ['id' => '01JQKM0000GGGG000000000001'],
            [],
        );
    }
}
