<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Presentation\Api\Provider\WarehouseCollectionProvider;
use App\Inventory\Presentation\Api\WarehouseResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(WarehouseCollectionProvider::class)]
final class WarehouseCollectionProviderTest extends TestCase
{
    private MessageBusInterface&MockObject $queryBus;
    private WarehouseCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->provider = new WarehouseCollectionProvider($this->queryBus);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createWarehouseDTO(): WarehouseDTO
    {
        return new WarehouseDTO(
            id: '00000000-0000-4000-8000-000000000010',
            tenantId: '00000000-0000-4000-8000-000000000001',
            code: 'WH001',
            name: 'Main Warehouse',
            address: ['street' => '123 Main St', 'city' => 'NY', 'state' => 'NY', 'postalCode' => '10001', 'country' => 'US'],
            priority: 1,
            isActive: true,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function provideReturnsWarehouseResources(): void
    {
        $dto = $this->createWarehouseDTO();
        $handledStamp = new HandledStamp([$dto], 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        self::assertCount(1, $result);
        self::assertInstanceOf(WarehouseResource::class, $result[0]);
        self::assertSame('WH001', $result[0]->code);
        self::assertSame('Main Warehouse', $result[0]->name);
    }

    // -----------------------------------------------------------------------
    // Missing tenant ID
    // -----------------------------------------------------------------------

    #[Test]
    public function provideThrowsWhenNoTenantId(): void
    {
        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        $this->provider->provide($operation, [], []);
    }

    // -----------------------------------------------------------------------
    // No handler found
    // -----------------------------------------------------------------------

    #[Test]
    public function provideThrowsWhenNoHandlerFound(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->queryBus->method('dispatch')->willReturn($envelope);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $this->provider->provide(
            $operation,
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    // -----------------------------------------------------------------------
    // Empty results
    // -----------------------------------------------------------------------

    #[Test]
    public function provideReturnsEmptyArrayWhenNoWarehouses(): void
    {
        $handledStamp = new HandledStamp([], 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->method('dispatch')->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001'],
        );

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Active filter
    // -----------------------------------------------------------------------

    #[Test]
    public function providePassesActiveFilter(): void
    {
        $dto = $this->createWarehouseDTO();
        $handledStamp = new HandledStamp([$dto], 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            [],
            [
                'tenant_id' => '00000000-0000-4000-8000-000000000001',
                'filters' => ['active' => true],
            ],
        );

        self::assertCount(1, $result);
    }
}
