<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Application\Command;

use App\Shipping\Application\Command\CreateShipment\CreateShipmentCommand;
use App\Shipping\Application\Command\CreateShipment\CreateShipmentHandler;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateShipmentHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    public function testHandleCreateShipment(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Shipment::class));

        $handler = new CreateShipmentHandler($repository);

        $command = new CreateShipmentCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            orderId: (string) Uuid::v4(),
            recipientName: 'John Doe',
            recipientAddress: ['street' => '123 Main St', 'city' => 'NYC'],
        );

        $shipment = $handler($command);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertTrue($shipment->status()->isPending());
        $this->assertSame('John Doe', $shipment->recipientName());
        $this->assertNull($shipment->rate());
    }

    public function testHandleCreateShipmentWithRate(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())->method('save');

        $handler = new CreateShipmentHandler($repository);

        $command = new CreateShipmentCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            orderId: (string) Uuid::v4(),
            recipientName: 'Jane Doe',
            recipientAddress: ['street' => '456 Oak Ave'],
            rateAmount: 1500,
            rateCurrency: 'USD',
            rateCarrier: 'ups',
            rateEstimatedDays: 3,
            rateServiceName: 'Ground Shipping',
        );

        $shipment = $handler($command);

        $this->assertNotNull($shipment->rate());
        $this->assertSame(3, $shipment->rate()->estimatedDays());
        $this->assertSame('Ground Shipping', $shipment->rate()->serviceName());
    }
}
