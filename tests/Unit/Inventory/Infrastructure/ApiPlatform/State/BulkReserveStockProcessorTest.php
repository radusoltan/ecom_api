<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Post;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockResult;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockResultItem;
use App\Inventory\Infrastructure\ApiPlatform\Resource\BulkStockOperationItem;
use App\Inventory\Infrastructure\ApiPlatform\Resource\BulkStockOperationResource;
use App\Inventory\Infrastructure\ApiPlatform\State\BulkReserveStockProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class BulkReserveStockProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private BulkReserveStockProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new BulkReserveStockProcessor($this->messageBus);
    }

    public function testProcessFullySuccessful(): void
    {
        $item = new BulkStockOperationItem(
            productId: '00000000-0000-4000-8000-000000000001',
            warehouseId: '00000000-0000-4000-8000-000000000002',
            quantity: 5,
        );

        $data = new BulkStockOperationResource(
            items: [$item],
            referenceId: 'ref-123',
        );

        $resultItem = new BulkReserveStockResultItem(
            productId: '00000000-0000-4000-8000-000000000001',
            warehouseId: '00000000-0000-4000-8000-000000000002',
            quantity: 5,
            success: true,
            availableQuantity: 100,
        );

        $result = new BulkReserveStockResult(
            referenceId: 'ref-123',
            totalItems: 1,
            successCount: 1,
            failureCount: 0,
            reservedItems: [$resultItem],
            failedItems: [],
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($result, 'handler')]);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        $this->assertInstanceOf(BulkStockOperationResource::class, $response);
        $this->assertSame('ref-123', $response->referenceId);
        $this->assertSame(1, $response->totalItems);
        $this->assertSame(1, $response->successCount);
        $this->assertSame(0, $response->failureCount);
        $this->assertSame('success', $response->status);
        $this->assertCount(1, $response->results);
    }

    public function testProcessPartiallySuccessful(): void
    {
        $items = [
            new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000001',
                warehouseId: '00000000-0000-4000-8000-000000000002',
                quantity: 5,
            ),
            new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000002',
                warehouseId: '00000000-0000-4000-8000-000000000003',
                quantity: 10,
            ),
        ];

        $data = new BulkStockOperationResource(items: $items, referenceId: 'ref-456');

        $reserved = new BulkReserveStockResultItem(
            productId: '00000000-0000-4000-8000-000000000001',
            warehouseId: '00000000-0000-4000-8000-000000000002',
            quantity: 5,
            success: true,
        );
        $failed = new BulkReserveStockResultItem(
            productId: '00000000-0000-4000-8000-000000000002',
            warehouseId: '00000000-0000-4000-8000-000000000003',
            quantity: 10,
            success: false,
            error: 'Insufficient stock',
            availableQuantity: 3,
        );

        $result = new BulkReserveStockResult(
            referenceId: 'ref-456',
            totalItems: 2,
            successCount: 1,
            failureCount: 1,
            reservedItems: [$reserved],
            failedItems: [$failed],
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($result, 'handler')]);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        $this->assertSame('partial', $response->status);
        $this->assertSame(1, $response->successCount);
        $this->assertSame(1, $response->failureCount);
        $this->assertCount(2, $response->results);
    }

    public function testProcessFullyFailed(): void
    {
        $data = new BulkStockOperationResource(
            items: [new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000001',
                warehouseId: '00000000-0000-4000-8000-000000000002',
                quantity: 5,
            )],
            referenceId: 'ref-789',
        );

        $failed = new BulkReserveStockResultItem(
            productId: '00000000-0000-4000-8000-000000000001',
            warehouseId: '00000000-0000-4000-8000-000000000002',
            quantity: 5,
            success: false,
            error: 'Out of stock',
        );

        $result = new BulkReserveStockResult(
            referenceId: 'ref-789',
            totalItems: 1,
            successCount: 0,
            failureCount: 1,
            reservedItems: [],
            failedItems: [$failed],
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($result, 'handler')]);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        $this->assertSame('failed', $response->status);
        $this->assertSame(0, $response->successCount);
    }

    public function testProcessThrowsBadRequestWhenTenantIdMissing(): void
    {
        $data = new BulkStockOperationResource(
            items: [new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000001',
                warehouseId: '00000000-0000-4000-8000-000000000002',
                quantity: 5,
            )],
            referenceId: 'ref-000',
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        $this->processor->process($data, new Post(), [], []);
    }

    public function testProcessRethrowsHttpException(): void
    {
        $data = new BulkStockOperationResource(
            items: [new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000001',
                warehouseId: '00000000-0000-4000-8000-000000000002',
                quantity: 5,
            )],
            referenceId: 'ref-err',
        );

        $httpException = new NotFoundHttpException('Product not found');
        $handlerFailed = new HandlerFailedException(
            new Envelope(new \stdClass()),
            [$httpException],
        );

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product not found');

        $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    public function testProcessWrapsNonHttpExceptionInBadRequest(): void
    {
        $data = new BulkStockOperationResource(
            items: [new BulkStockOperationItem(
                productId: '00000000-0000-4000-8000-000000000001',
                warehouseId: '00000000-0000-4000-8000-000000000002',
                quantity: 5,
            )],
            referenceId: 'ref-gen',
        );

        $genericException = new \RuntimeException('Something went wrong');
        $handlerFailed = new HandlerFailedException(
            new Envelope(new \stdClass()),
            [$genericException],
        );

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Something went wrong');

        $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );
    }
}
