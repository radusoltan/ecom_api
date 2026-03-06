<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Application\Command\UpdateTracking\UpdateTrackingCommand;
use App\Shipping\Application\Command\UpdateTracking\UpdateTrackingHandler;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(UpdateTrackingHandler::class)]
final class UpdateTrackingHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeShipment(): Shipment
    {
        return Shipment::create(
            ShipmentId::fromString((string) Uuid::v4()),
            TenantId::fromString(self::TENANT_ID),
            (string) Uuid::v4(),
            'John Doe',
            ['street' => '123 Main St'],
        );
    }

    /**
     * Returns a shipment that has already been dispatched, so it can
     * legally transition to IN_TRANSIT or DELIVERED.
     */
    private function makeDispatchedShipment(): Shipment
    {
        $shipment = $this->makeShipment();
        $shipment->dispatch(
            CarrierCode::ups(),
            TrackingNumber::fromString('1Z999AA10123456784'),
        );

        return $shipment;
    }

    // ---------------------------------------------------------------
    // No status change
    // ---------------------------------------------------------------

    public function testHandleUpdateTrackingCallsUpdateTrackingAndSaves(): void
    {
        $shipmentId = (string) Uuid::v4();
        $shipment = $this->makeShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByIdAndTenant')
            ->with(
                $this->callback(fn (ShipmentId $id) => $id->toString() === $shipmentId),
                $this->callback(fn (TenantId $tid) => self::TENANT_ID === $tid->toString()),
            )
            ->willReturn($shipment);
        $repository->expects($this->once())
            ->method('save')
            ->with($shipment);

        $handler = new UpdateTrackingHandler($repository);

        $handler(new UpdateTrackingCommand(
            shipmentId: $shipmentId,
            tenantId: self::TENANT_ID,
            location: 'Hamburg Hub',
            statusNote: 'Package in transit',
            newStatus: null,
        ));
    }

    public function testHandleUpdateTrackingWithoutStatusChangeDoesNotTransitionStatus(): void
    {
        $shipment = $this->makeShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn($shipment);
        $repository->method('save');

        $handler = new UpdateTrackingHandler($repository);

        $handler(new UpdateTrackingCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            location: 'Munich',
            statusNote: 'Arrived at facility',
            newStatus: null,
        ));

        $this->assertTrue($shipment->status()->isPending());
    }

    // ---------------------------------------------------------------
    // IN_TRANSIT transition
    // ---------------------------------------------------------------

    public function testHandleUpdateTrackingWithInTransitStatusCallsMarkInTransit(): void
    {
        $shipment = $this->makeDispatchedShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn($shipment);
        $repository->expects($this->once())
            ->method('save')
            ->with($shipment);

        $handler = new UpdateTrackingHandler($repository);

        $handler(new UpdateTrackingCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            location: 'Berlin Distribution Center',
            statusNote: 'Out for delivery',
            newStatus: ShipmentStatus::IN_TRANSIT,
        ));

        $this->assertTrue($shipment->status()->isInTransit());
    }

    // ---------------------------------------------------------------
    // DELIVERED transition
    // ---------------------------------------------------------------

    public function testHandleUpdateTrackingWithDeliveredStatusCallsMarkDelivered(): void
    {
        $shipment = $this->makeDispatchedShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn($shipment);
        $repository->expects($this->once())
            ->method('save')
            ->with($shipment);

        $handler = new UpdateTrackingHandler($repository);

        $handler(new UpdateTrackingCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            location: 'Customer Address',
            statusNote: 'Delivered to recipient',
            newStatus: ShipmentStatus::DELIVERED,
        ));

        $this->assertTrue($shipment->status()->isDelivered());
    }

    // ---------------------------------------------------------------
    // FAILED transition
    // ---------------------------------------------------------------

    public function testHandleUpdateTrackingWithFailedStatusCallsMarkFailed(): void
    {
        $shipment = $this->makeDispatchedShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn($shipment);
        $repository->expects($this->once())->method('save');

        $handler = new UpdateTrackingHandler($repository);

        $handler(new UpdateTrackingCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            location: 'Customs',
            statusNote: 'Delivery failed: address not found',
            newStatus: ShipmentStatus::FAILED,
        ));

        $this->assertTrue($shipment->status()->isFailed());
    }

    // ---------------------------------------------------------------
    // Unrecognised status (default branch — no transition called)
    // ---------------------------------------------------------------

    public function testHandleUpdateTrackingWithUnrecognisedStatusDoesNotTransition(): void
    {
        $shipment = $this->makeShipment();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn($shipment);
        $repository->method('save');

        $handler = new UpdateTrackingHandler($repository);

        // DISPATCHED is not handled in the match → hits default → no transition
        $handler(new UpdateTrackingCommand(
            shipmentId: (string) Uuid::v4(),
            tenantId: self::TENANT_ID,
            location: 'Warehouse',
            statusNote: 'Status updated',
            newStatus: ShipmentStatus::DISPATCHED,
        ));

        $this->assertTrue($shipment->status()->isPending());
    }

    // ---------------------------------------------------------------
    // Not-found case
    // ---------------------------------------------------------------

    public function testHandleThrowsRuntimeExceptionWhenShipmentNotFound(): void
    {
        $shipmentId = (string) Uuid::v4();

        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn(null);

        $handler = new UpdateTrackingHandler($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($shipmentId);

        $handler(new UpdateTrackingCommand(
            shipmentId: $shipmentId,
            tenantId: self::TENANT_ID,
            location: 'Unknown',
            statusNote: 'Update attempt',
            newStatus: null,
        ));
    }

    public function testHandleNotFoundDoesNotCallSave(): void
    {
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->method('findByIdAndTenant')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $handler = new UpdateTrackingHandler($repository);

        try {
            $handler(new UpdateTrackingCommand(
                shipmentId: (string) Uuid::v4(),
                tenantId: self::TENANT_ID,
                location: 'Unknown',
                statusNote: 'Update attempt',
                newStatus: null,
            ));
        } catch (\RuntimeException) {
            // Expected — verify save was never called (assertion above)
        }
    }
}
