<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Application\Command\CancelShipment\CancelShipmentCommand;
use App\Shipping\Application\Command\CancelShipment\CancelShipmentHandler;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CancelShipmentHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    public function testHandleCancelShipment(): void
    {
        $shipmentId = (string) Uuid::v4();
        $shipment = Shipment::create(
            ShipmentId::fromString($shipmentId),
            TenantId::fromString(self::TENANT_ID),
            (string) Uuid::v4(),
            'John Doe',
            ['street' => '123 Main St'],
        );

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdAndTenant')
            ->willReturn($shipment);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Shipment $s) => $s->status()->isCancelled()));

        $handler = new CancelShipmentHandler($repository);

        $handler(new CancelShipmentCommand(
            shipmentId: $shipmentId,
            tenantId: self::TENANT_ID,
        ));

        $this->assertTrue($shipment->status()->isCancelled());
    }

    public function testThrowsWhenShipmentNotFound(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn(null);

        $handler = new CancelShipmentHandler($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $handler(new CancelShipmentCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
        ));
    }
}
