<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Event\ShipmentCancelled;
use App\Shipping\Domain\Event\ShipmentCreated;
use App\Shipping\Domain\Event\ShipmentDelivered;
use App\Shipping\Domain\Event\ShipmentDispatched;
use App\Shipping\Domain\Event\ShipmentInTransit;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipmentCreated::class)]
#[CoversClass(ShipmentDispatched::class)]
#[CoversClass(ShipmentDelivered::class)]
#[CoversClass(ShipmentInTransit::class)]
#[CoversClass(ShipmentCancelled::class)]
final class ShipmentEventsTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const SHIPMENT_ID = '12345678-1234-4000-8000-000000000001';

    // ---------------------------------------------------------------
    // ShipmentCreated
    // ---------------------------------------------------------------

    public function testShipmentCreatedStoresAllProperties(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $orderId = 'order-abc-123';
        $recipientName = 'Jane Doe';
        $occurredOn = new \DateTimeImmutable('2026-03-04T10:00:00Z');

        $event = new ShipmentCreated(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            orderId: $orderId,
            recipientName: $recipientName,
            occurredOn: $occurredOn,
        );

        self::assertSame($shipmentId, $event->shipmentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($orderId, $event->orderId);
        self::assertSame($recipientName, $event->recipientName);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    public function testShipmentCreatedShipmentIdIsAccessible(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);

        $event = new ShipmentCreated(
            shipmentId: $shipmentId,
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: 'order-1',
            recipientName: 'John Smith',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertTrue($event->shipmentId->equals($shipmentId));
    }

    public function testShipmentCreatedTenantIdIsAccessible(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ShipmentCreated(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: $tenantId,
            orderId: 'order-1',
            recipientName: 'John Smith',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertTrue($event->tenantId->equals($tenantId));
    }

    // ---------------------------------------------------------------
    // ShipmentDispatched
    // ---------------------------------------------------------------

    public function testShipmentDispatchedStoresAllProperties(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $carrierCode = CarrierCode::ups();
        $trackingNumber = TrackingNumber::fromString('1Z999AA10123456784');
        $shippedAt = new \DateTimeImmutable('2026-03-04T12:00:00Z');
        $occurredOn = new \DateTimeImmutable('2026-03-04T12:00:01Z');

        $event = new ShipmentDispatched(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            carrierCode: $carrierCode,
            trackingNumber: $trackingNumber,
            shippedAt: $shippedAt,
            occurredOn: $occurredOn,
        );

        self::assertSame($shipmentId, $event->shipmentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($carrierCode, $event->carrierCode);
        self::assertSame($trackingNumber, $event->trackingNumber);
        self::assertSame($shippedAt, $event->shippedAt);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    public function testShipmentDispatchedCarrierCodeIsAccessible(): void
    {
        $event = new ShipmentDispatched(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            carrierCode: CarrierCode::fedex(),
            trackingNumber: TrackingNumber::fromString('FEDEX123456789012'),
            shippedAt: new \DateTimeImmutable(),
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertTrue($event->carrierCode->equals(CarrierCode::fedex()));
    }

    public function testShipmentDispatchedTrackingNumberIsAccessible(): void
    {
        $tracking = TrackingNumber::fromString('DHL-TRACKING-001');

        $event = new ShipmentDispatched(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            carrierCode: CarrierCode::dhl(),
            trackingNumber: $tracking,
            shippedAt: new \DateTimeImmutable(),
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame($tracking->toString(), $event->trackingNumber->toString());
    }

    // ---------------------------------------------------------------
    // ShipmentDelivered
    // ---------------------------------------------------------------

    public function testShipmentDeliveredStoresAllProperties(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $deliveredAt = new \DateTimeImmutable('2026-03-05T14:30:00Z');
        $occurredOn = new \DateTimeImmutable('2026-03-05T14:30:01Z');

        $event = new ShipmentDelivered(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            deliveredAt: $deliveredAt,
            occurredOn: $occurredOn,
        );

        self::assertSame($shipmentId, $event->shipmentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($deliveredAt, $event->deliveredAt);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    public function testShipmentDeliveredAtTimestampIsPreserved(): void
    {
        $deliveredAt = new \DateTimeImmutable('2026-03-05T09:15:00Z');

        $event = new ShipmentDelivered(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            deliveredAt: $deliveredAt,
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame('2026-03-05T09:15:00+00:00', $event->deliveredAt->format(\DateTimeInterface::ATOM));
    }

    // ---------------------------------------------------------------
    // ShipmentInTransit
    // ---------------------------------------------------------------

    public function testShipmentInTransitStoresAllProperties(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $location = 'Frankfurt Hub';
        $statusNote = 'Package arrived at sorting facility';
        $occurredOn = new \DateTimeImmutable('2026-03-04T18:00:00Z');

        $event = new ShipmentInTransit(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            location: $location,
            statusNote: $statusNote,
            occurredOn: $occurredOn,
        );

        self::assertSame($shipmentId, $event->shipmentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($location, $event->location);
        self::assertSame($statusNote, $event->statusNote);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    public function testShipmentInTransitLocationAndNoteAreAccessible(): void
    {
        $event = new ShipmentInTransit(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            location: 'Berlin Distribution Center',
            statusNote: 'Out for delivery',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame('Berlin Distribution Center', $event->location);
        self::assertSame('Out for delivery', $event->statusNote);
    }

    public function testShipmentInTransitAllowsEmptyLocation(): void
    {
        $event = new ShipmentInTransit(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            location: '',
            statusNote: 'Shipment is in transit',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame('', $event->location);
    }

    // ---------------------------------------------------------------
    // ShipmentCancelled
    // ---------------------------------------------------------------

    public function testShipmentCancelledStoresAllProperties(): void
    {
        $shipmentId = ShipmentId::fromString(self::SHIPMENT_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $cancelledAt = new \DateTimeImmutable('2026-03-04T11:00:00Z');
        $occurredOn = new \DateTimeImmutable('2026-03-04T11:00:01Z');

        $event = new ShipmentCancelled(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            cancelledAt: $cancelledAt,
            occurredOn: $occurredOn,
        );

        self::assertSame($shipmentId, $event->shipmentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($cancelledAt, $event->cancelledAt);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    public function testShipmentCancelledAtTimestampIsPreserved(): void
    {
        $cancelledAt = new \DateTimeImmutable('2026-03-04T08:45:00Z');

        $event = new ShipmentCancelled(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            cancelledAt: $cancelledAt,
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame('2026-03-04T08:45:00+00:00', $event->cancelledAt->format(\DateTimeInterface::ATOM));
    }

    public function testShipmentCancelledOccurredOnIsAccessible(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-03-04T11:00:01Z');

        $event = new ShipmentCancelled(
            shipmentId: ShipmentId::fromString(self::SHIPMENT_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            cancelledAt: new \DateTimeImmutable(),
            occurredOn: $occurredOn,
        );

        self::assertSame($occurredOn, $event->occurredOn);
    }
}
