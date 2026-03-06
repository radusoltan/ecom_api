<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Infrastructure\Persistence\Doctrine\Entity;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Infrastructure\Persistence\Doctrine\Entity\ReturnRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ReturnRequestEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $returnRequest = ReturnRequest::create(
            ReturnRequestId::generate(),
            TenantId::generate(),
            OrderId::generate(),
            ReturnReason::fromString('Product damaged during shipping'),
        );

        $entity = ReturnRequestEntity::fromDomainModel($returnRequest);

        self::assertSame('requested', $entity->getStatus());
        self::assertSame('Product damaged during shipping', $entity->getReason());
        self::assertNull($entity->getWarehouseId());
        self::assertNull($entity->getRefundAmount());
        self::assertNull($entity->isResellable());

        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($returnRequest->id()));
        self::assertSame('requested', $restored->status()->value());
    }

    public function testUpdateFromDomainModel(): void
    {
        $returnRequest = ReturnRequest::create(
            ReturnRequestId::generate(),
            TenantId::generate(),
            OrderId::generate(),
            ReturnReason::fromString('Item does not match description'),
        );

        $entity = ReturnRequestEntity::fromDomainModel($returnRequest);
        self::assertSame('requested', $entity->getStatus());

        $returnRequest->approve();
        $entity->updateFromDomainModel($returnRequest);

        self::assertSame('approved', $entity->getStatus());
    }
}
