<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Event\ShipmentCancelled;
use App\Shipping\Domain\Event\ShipmentCreated;
use App\Shipping\Domain\Event\ShipmentDelivered;
use App\Shipping\Domain\Event\ShipmentDispatched;
use App\Shipping\Domain\Event\ShipmentInTransit;
use App\Shipping\Domain\Exception\InvalidShipmentTransitionException;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class ShipmentTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440099';

    public function testCreateShipment(): void
    {
        $shipment = $this->createShipment();

        $this->assertSame(self::ORDER_ID, $shipment->orderId());
        $this->assertSame('John Doe', $shipment->recipientName());
        $this->assertTrue($shipment->status()->isPending());
        $this->assertNull($shipment->carrier());
        $this->assertNull($shipment->trackingNumber());
        $this->assertNull($shipment->rate());
        $this->assertNull($shipment->shippedAt());
        $this->assertNull($shipment->deliveredAt());
        $this->assertNotNull($shipment->createdAt());
    }

    public function testCreateWithRate(): void
    {
        $rate = ShippingRate::create(Money::of(15, 'USD'), CarrierCode::ups(), 3, 'Ground');

        $shipment = Shipment::create(
            ShipmentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            self::ORDER_ID,
            'Jane Doe',
            ['street' => '123 Main St', 'city' => 'NYC'],
            $rate,
        );

        $this->assertNotNull($shipment->rate());
        $this->assertTrue($shipment->rate()->equals($rate));
    }

    public function testRejectsEmptyOrderId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order ID cannot be empty');

        Shipment::create(
            ShipmentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            '',
            'John Doe',
            ['street' => '123 Main St'],
        );
    }

    public function testRejectsEmptyRecipientName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient name cannot be empty');

        Shipment::create(
            ShipmentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            self::ORDER_ID,
            '',
            ['street' => '123 Main St'],
        );
    }

    public function testDispatch(): void
    {
        $shipment = $this->createShipment();
        $carrier = CarrierCode::ups();
        $tracking = TrackingNumber::fromString('1Z999AA10123456784');

        $shipment->dispatch($carrier, $tracking);

        $this->assertTrue($shipment->status()->isDispatched());
        $this->assertTrue($shipment->carrier()->equals($carrier));
        $this->assertTrue($shipment->trackingNumber()->equals($tracking));
        $this->assertNotNull($shipment->shippedAt());
    }

    public function testFullLifecycle(): void
    {
        $shipment = $this->createShipment();

        $shipment->dispatch(CarrierCode::fedex(), TrackingNumber::fromString('FX123'));
        $this->assertTrue($shipment->status()->isDispatched());

        $shipment->markInTransit();
        $this->assertTrue($shipment->status()->isInTransit());

        $shipment->markDelivered();
        $this->assertTrue($shipment->status()->isDelivered());
        $this->assertNotNull($shipment->deliveredAt());
    }

    public function testCancelPendingShipment(): void
    {
        $shipment = $this->createShipment();

        $shipment->cancel();

        $this->assertTrue($shipment->status()->isCancelled());
    }

    public function testCannotCancelDeliveredShipment(): void
    {
        $shipment = $this->createShipment();
        $shipment->dispatch(CarrierCode::dhl(), TrackingNumber::fromString('DHL001'));
        $shipment->markInTransit();
        $shipment->markDelivered();

        $this->expectException(InvalidShipmentTransitionException::class);
        $shipment->cancel();
    }

    public function testCannotDispatchCancelledShipment(): void
    {
        $shipment = $this->createShipment();
        $shipment->cancel();

        $this->expectException(InvalidShipmentTransitionException::class);
        $shipment->dispatch(CarrierCode::ups(), TrackingNumber::fromString('UPS001'));
    }

    public function testMarkFailed(): void
    {
        $shipment = $this->createShipment();
        $shipment->dispatch(CarrierCode::usps(), TrackingNumber::fromString('USPS1'));

        $shipment->markFailed('Package lost');

        $this->assertTrue($shipment->status()->isFailed());
    }

    public function testUpdateTracking(): void
    {
        $shipment = $this->createShipment();
        $shipment->dispatch(CarrierCode::gls(), TrackingNumber::fromString('GLS1'));
        $shipment->markInTransit();

        $shipment->updateTracking('Berlin Hub', 'In sorting facility');

        $this->assertNotNull($shipment->updatedAt());
    }

    public function testRecordsCreatedEvent(): void
    {
        $shipment = $this->createShipment();

        $events = $shipment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShipmentCreated::class, $events[0]);
    }

    public function testRecordsDispatchEvent(): void
    {
        $shipment = $this->createShipment();
        $shipment->popEvents(); // clear creation event

        $shipment->dispatch(CarrierCode::ups(), TrackingNumber::fromString('UPS123'));

        $events = $shipment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShipmentDispatched::class, $events[0]);
    }

    public function testRecordsCancelEvent(): void
    {
        $shipment = $this->createShipment();
        $shipment->popEvents();

        $shipment->cancel();

        $events = $shipment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShipmentCancelled::class, $events[0]);
    }

    public function testRecordsDeliveredEvent(): void
    {
        $shipment = $this->createShipment();
        $shipment->dispatch(CarrierCode::dhl(), TrackingNumber::fromString('DHL1'));
        $shipment->markInTransit();
        $shipment->popEvents();

        $shipment->markDelivered();

        $events = $shipment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShipmentDelivered::class, $events[0]);
    }

    public function testRecordsInTransitEvent(): void
    {
        $shipment = $this->createShipment();
        $shipment->dispatch(CarrierCode::dpd(), TrackingNumber::fromString('DPD1'));
        $shipment->popEvents();

        $shipment->markInTransit();

        $events = $shipment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ShipmentInTransit::class, $events[0]);
    }

    public function testReconstituteFromPersistence(): void
    {
        $id = ShipmentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $carrier = CarrierCode::ups();
        $tracking = TrackingNumber::fromString('UPS999');
        $rate = ShippingRate::create(Money::of(10, 'EUR'), CarrierCode::ups(), 2, 'Express');
        $shippedAt = new \DateTimeImmutable('2026-01-15');
        $createdAt = new \DateTimeImmutable('2026-01-14');

        $shipment = Shipment::reconstituteFromPersistence(
            $id, $tenantId, self::ORDER_ID,
            ShipmentStatus::dispatched(),
            'Reconstituted User',
            ['street' => '456 Oak Ave'],
            $carrier, $tracking, $rate,
            $shippedAt, null, $createdAt, null,
        );

        $this->assertTrue($shipment->id()->equals($id));
        $this->assertTrue($shipment->status()->isDispatched());
        $this->assertSame('Reconstituted User', $shipment->recipientName());
        $this->assertSame($shippedAt, $shipment->shippedAt());
        $this->assertEmpty($shipment->popEvents());
    }

    private function createShipment(): Shipment
    {
        return Shipment::create(
            ShipmentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            self::ORDER_ID,
            'John Doe',
            ['street' => '123 Main St', 'city' => 'New York', 'zip' => '10001'],
        );
    }
}
