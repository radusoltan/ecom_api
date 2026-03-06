<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use App\Inventory\Application\Query\CheckStockAvailability\ItemAvailabilityResult;
use App\Inventory\Application\Query\CheckStockAvailability\StockAvailabilityResult;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityItemResource;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityItemResultResource;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockAvailabilityResource;
use App\Inventory\Infrastructure\ApiPlatform\State\CheckStockAvailabilityProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[\PHPUnit\Framework\Attributes\CoversClass(CheckStockAvailabilityProcessor::class)]
final class CheckStockAvailabilityProcessorTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private Operation $operation;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';
    private string $productId = 'b5e4c3d2-0000-4000-8000-000000000020';

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
    }

    private function buildProcessor(): CheckStockAvailabilityProcessor
    {
        return new CheckStockAvailabilityProcessor($this->messageBus);
    }

    private function buildAvailabilityResult(bool $available = true): StockAvailabilityResult
    {
        return new StockAvailabilityResult(
            available: $available,
            items: [
                new ItemAvailabilityResult(
                    productId: $this->productId,
                    variantId: null,
                    requestedQuantity: 2,
                    availableQuantity: 50,
                    available: $available,
                ),
            ],
        );
    }

    private function dispatchReturns(StockAvailabilityResult $result): void
    {
        $handledStamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);
    }

    // ---------------------------------------------------------------
    // Successful check with StockAvailabilityItemResource items
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itProcessesItemResourcesAndReturnsAvailableTrue(): void
    {
        $item = new StockAvailabilityItemResource(productId: $this->productId, quantity: 2);
        $data = new StockAvailabilityResource(items: [$item]);

        $this->dispatchReturns($this->buildAvailabilityResult(true));

        $processor = $this->buildProcessor();
        $response = $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);

        self::assertInstanceOf(StockAvailabilityResource::class, $response);
        self::assertTrue($response->available);
        self::assertCount(1, $response->items);
        self::assertInstanceOf(StockAvailabilityItemResultResource::class, $response->items[0]);
        self::assertSame($this->productId, $response->items[0]->productId);
        self::assertSame(50, $response->items[0]->availableQuantity);
        self::assertTrue($response->items[0]->available);
    }

    // ---------------------------------------------------------------
    // Array format items
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itProcessesArrayItemsCorrectly(): void
    {
        $item = ['productId' => $this->productId, 'variantId' => null, 'quantity' => 3];
        $data = new StockAvailabilityResource(items: [$item]);

        $this->dispatchReturns($this->buildAvailabilityResult(true));

        $processor = $this->buildProcessor();
        $response = $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);

        self::assertTrue($response->available);
    }

    // ---------------------------------------------------------------
    // StockAvailabilityItemResultResource items (from re-check scenario)
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itProcessesResultResourceItemsCorrectly(): void
    {
        $item = new StockAvailabilityItemResultResource(
            productId: $this->productId,
            variantId: 'var-1',
            requestedQuantity: 1,
            availableQuantity: 10,
            available: true,
        );
        $data = new StockAvailabilityResource(items: [$item]);

        $this->dispatchReturns($this->buildAvailabilityResult(true));

        $processor = $this->buildProcessor();
        $response = $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);

        self::assertTrue($response->available);
    }

    // ---------------------------------------------------------------
    // Empty items → BadRequest
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestWhenItemsAreNull(): void
    {
        $data = new StockAvailabilityResource(items: null);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/cannot be empty/');

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestWhenItemsAreEmpty(): void
    {
        $data = new StockAvailabilityResource(items: []);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    // ---------------------------------------------------------------
    // Missing / invalid item fields
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestWhenArrayItemMissingProductId(): void
    {
        $data = new StockAvailabilityResource(items: [['quantity' => 2]]);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/productId is required/');

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestWhenArrayItemMissingQuantity(): void
    {
        $data = new StockAvailabilityResource(items: [['productId' => $this->productId]]);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/quantity is required/');

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestForInvalidItemFormat(): void
    {
        $data = new StockAvailabilityResource(items: ['not-an-object-or-array']);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/Invalid item format/');

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    // ---------------------------------------------------------------
    // Tenant ID errors
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsBadRequestWhenTenantIdMissing(): void
    {
        $data = new StockAvailabilityResource(items: [['productId' => $this->productId, 'quantity' => 1]]);

        $processor = $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);

        $processor->process($data, $this->operation, [], []);
    }

    // ---------------------------------------------------------------
    // Handler returns no HandledStamp → RuntimeException
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itThrowsRuntimeExceptionWhenHandlerReturnsNoStamp(): void
    {
        $item = new StockAvailabilityItemResource(productId: $this->productId, quantity: 1);
        $data = new StockAvailabilityResource(items: [$item]);

        // Envelope with no HandledStamp
        $envelope = new Envelope(new \stdClass());
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $processor = $this->buildProcessor();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not return a result/');

        $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);
    }

    // ---------------------------------------------------------------
    // Available = false
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturnsAvailableFalseWhenStockInsufficient(): void
    {
        $item = new StockAvailabilityItemResource(productId: $this->productId, quantity: 100);
        $data = new StockAvailabilityResource(items: [$item]);

        $this->dispatchReturns($this->buildAvailabilityResult(false));

        $processor = $this->buildProcessor();
        $response = $processor->process($data, $this->operation, [], ['tenant_id' => $this->tenantId]);

        self::assertFalse($response->available);
    }
}
