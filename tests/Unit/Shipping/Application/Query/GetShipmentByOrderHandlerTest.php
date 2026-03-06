<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Application\Query\GetShipmentByOrder\GetShipmentByOrderHandler;
use App\Shipping\Application\Query\GetShipmentByOrder\GetShipmentByOrderQuery;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(GetShipmentByOrderHandler::class)]
final class GetShipmentByOrderHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // ---------------------------------------------------------------
    // Found case
    // ---------------------------------------------------------------

    public function testInvokeReturnsShipmentWhenFound(): void
    {
        $orderId = (string) Uuid::v4();
        $shipment = Shipment::create(
            ShipmentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            $orderId,
            'Jane Doe',
            ['street' => '42 Main St'],
        );

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId, $this->isInstanceOf(TenantId::class))
            ->willReturn($shipment);

        $handler = new GetShipmentByOrderHandler($repository);

        $result = $handler(new GetShipmentByOrderQuery(
            orderId: $orderId,
            tenantId: self::TENANT_ID,
        ));

        $this->assertSame($shipment, $result);
    }

    public function testInvokePassesOrderIdToRepository(): void
    {
        $orderId = 'specific-order-id-abc';

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId, $this->anything())
            ->willReturn(null);

        $handler = new GetShipmentByOrderHandler($repository);

        $handler(new GetShipmentByOrderQuery(
            orderId: $orderId,
            tenantId: self::TENANT_ID,
        ));
    }

    public function testInvokePassesTenantIdToRepository(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByOrderId')
            ->with(
                $this->anything(),
                $this->callback(fn (TenantId $tid) => self::TENANT_ID === $tid->toString()),
            )
            ->willReturn(null);

        $handler = new GetShipmentByOrderHandler($repository);

        $handler(new GetShipmentByOrderQuery(
            orderId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
        ));
    }

    // ---------------------------------------------------------------
    // Not-found case
    // ---------------------------------------------------------------

    public function testInvokeReturnsNullWhenShipmentNotFound(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByOrderId')->willReturn(null);

        $handler = new GetShipmentByOrderHandler($repository);

        $result = $handler(new GetShipmentByOrderQuery(
            orderId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
        ));

        $this->assertNull($result);
    }

    public function testInvokeCallsRepositoryExactlyOnce(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByOrderId')
            ->willReturn(null);

        $handler = new GetShipmentByOrderHandler($repository);

        $handler(new GetShipmentByOrderQuery(
            orderId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
        ));
    }
}
