<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Persistence\Doctrine\Entity;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\FulfillmentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class FulfillmentEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $id = FulfillmentId::generate();
        $orderId = OrderId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();

        $fulfillment = Fulfillment::start($id, $orderId, $warehouseId, $tenantId);
        $fulfillment->startPicking();
        $fulfillment->startPacking();
        $fulfillment->ship('DHL', 'TRACK-123');

        $entity = FulfillmentEntity::fromDomainModel($fulfillment);

        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($orderId->toString(), $entity->getOrderId());
        self::assertSame($warehouseId->toString(), $entity->getWarehouseId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('shipping', $entity->getStatus());
        self::assertSame('DHL', $entity->getCarrier());
        self::assertSame('TRACK-123', $entity->getTrackingNumber());
        self::assertNotNull($entity->getAssignedAt());
        self::assertNotNull($entity->getPickingStartedAt());
        self::assertNotNull($entity->getPackingStartedAt());
        self::assertNotNull($entity->getShippedAt());
        self::assertNull($entity->getDeliveredAt());
        self::assertNull($entity->getCancelledAt());
        self::assertNull($entity->getFailedAt());
        self::assertNull($entity->getFailureReason());

        // Convert back
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($id));
        self::assertTrue($restored->status()->equals(FulfillmentStatus::shipping()));
        self::assertSame('DHL', $restored->carrier());
    }

    public function testUpdateFromDomainModel(): void
    {
        $fulfillment = Fulfillment::start(FulfillmentId::generate(), OrderId::generate(), WarehouseId::generate(), TenantId::generate());

        $entity = FulfillmentEntity::fromDomainModel($fulfillment);
        self::assertSame('assigned', $entity->getStatus());

        $fulfillment->startPicking();
        $entity->updateFromDomainModel($fulfillment);

        self::assertSame('picking', $entity->getStatus());
    }
}
