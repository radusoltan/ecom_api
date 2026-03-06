<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use App\Shipping\Infrastructure\Persistence\Doctrine\Entity\ShipmentEntity;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class ShipmentEntityTest extends TestCase
{
    public function testFromDomainModelRoundtripMinimal(): void
    {
        $id = ShipmentId::generate();
        $tenantId = TenantId::generate();
        $orderId = 'order-00000000-0000-4000-8000-000000000001';
        $recipientName = 'John Doe';
        $recipientAddress = ['street' => '123 Main St', 'city' => 'Springfield', 'country' => 'US'];
        $createdAt = new \DateTimeImmutable('2026-01-15T08:00:00+00:00');

        $shipment = Shipment::reconstituteFromPersistence(
            $id,
            $tenantId,
            $orderId,
            ShipmentStatus::pending(),
            $recipientName,
            $recipientAddress,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            null,
        );

        $entity = ShipmentEntity::fromDomainModel($shipment);
        $restored = $entity->toDomainModel();

        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame($orderId, $restored->orderId());
        self::assertSame('pending', $restored->status()->value());
        self::assertSame($recipientName, $restored->recipientName());
        self::assertSame($recipientAddress, $restored->recipientAddress());
        self::assertNull($restored->carrier());
        self::assertNull($restored->trackingNumber());
        self::assertNull($restored->rate());
        self::assertNull($restored->shippedAt());
        self::assertNull($restored->deliveredAt());
        self::assertSame($createdAt, $restored->createdAt());
        self::assertNull($restored->updatedAt());
    }

    public function testFromDomainModelRoundtripWithCarrierTrackingAndRate(): void
    {
        $id = ShipmentId::generate();
        $tenantId = TenantId::generate();
        $carrier = CarrierCode::ups();
        $trackingNumber = TrackingNumber::fromString('1Z999AA10123456784');
        $rate = ShippingRate::create(
            Money::ofMinor(1299, 'USD'),
            $carrier,
            3,
            'UPS Ground',
        );
        $shippedAt = new \DateTimeImmutable('2026-01-16T09:00:00+00:00');
        $deliveredAt = new \DateTimeImmutable('2026-01-19T14:30:00+00:00');
        $createdAt = new \DateTimeImmutable('2026-01-15T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-19T14:35:00+00:00');

        $shipment = Shipment::reconstituteFromPersistence(
            $id,
            $tenantId,
            'order-00000000-0000-4000-8000-000000000002',
            ShipmentStatus::delivered(),
            'Jane Smith',
            ['street' => '456 Oak Ave', 'city' => 'Portland'],
            $carrier,
            $trackingNumber,
            $rate,
            $shippedAt,
            $deliveredAt,
            $createdAt,
            $updatedAt,
        );

        $entity = ShipmentEntity::fromDomainModel($shipment);
        $restored = $entity->toDomainModel();

        self::assertSame('delivered', $restored->status()->value());
        self::assertSame('ups', $restored->carrier()?->toString());
        self::assertSame('1Z999AA10123456784', $restored->trackingNumber()?->toString());
        self::assertNotNull($restored->rate());
        self::assertSame(1299, $restored->rate()->amount()->getMinorAmount()->toInt());
        self::assertSame('USD', $restored->rate()->amount()->getCurrency()->getCurrencyCode());
        self::assertSame(3, $restored->rate()->estimatedDays());
        self::assertSame('UPS Ground', $restored->rate()->serviceName());
        self::assertSame($shippedAt, $restored->shippedAt());
        self::assertSame($deliveredAt, $restored->deliveredAt());
        self::assertSame($updatedAt, $restored->updatedAt());
    }

    public function testUpdateFromDomainModelChangesMutableFields(): void
    {
        $id = ShipmentId::generate();
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-01-10T00:00:00+00:00');

        $original = Shipment::reconstituteFromPersistence(
            $id,
            $tenantId,
            'order-00000000-0000-4000-8000-000000000003',
            ShipmentStatus::pending(),
            'Bob Builder',
            ['street' => '789 Pine Rd'],
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            null,
        );

        $entity = ShipmentEntity::fromDomainModel($original);

        // Simulate a dispatch update
        $updatedAt = new \DateTimeImmutable('2026-01-11T10:00:00+00:00');
        $carrier = CarrierCode::fedex();
        $tracking = TrackingNumber::fromString('TRACK12345');
        $shippedAt = new \DateTimeImmutable('2026-01-11T09:55:00+00:00');

        $updatedShipment = Shipment::reconstituteFromPersistence(
            $id,
            $tenantId,
            'order-00000000-0000-4000-8000-000000000003',
            ShipmentStatus::dispatched(),
            'Bob Builder',
            ['street' => '789 Pine Rd'],
            $carrier,
            $tracking,
            null,
            $shippedAt,
            null,
            $createdAt,
            $updatedAt,
        );

        $entity->updateFromDomainModel($updatedShipment);
        $restored = $entity->toDomainModel();

        self::assertSame('dispatched', $restored->status()->value());
        self::assertSame('fedex', $restored->carrier()?->toString());
        self::assertSame('TRACK12345', $restored->trackingNumber()?->toString());
        self::assertSame($shippedAt, $restored->shippedAt());
        self::assertSame($updatedAt, $restored->updatedAt());
        // Id and orderId and createdAt should be unchanged (set only in fromDomainModel)
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($createdAt, $restored->createdAt());
    }
}
