<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PaymentEntityIdempotencyTest extends TestCase
{
    public function testFromDomainModelPreservesIdempotencyKey(): void
    {
        $idempotencyKey = 'idem_entity_test_' . bin2hex(random_bytes(8));

        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            idempotencyKey: $idempotencyKey
        );

        $entity = PaymentEntity::fromDomainModel($payment);

        $this->assertSame($idempotencyKey, $entity->getIdempotencyKey());
    }

    public function testFromDomainModelWithNullIdempotencyKey(): void
    {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $entity = PaymentEntity::fromDomainModel($payment);

        $this->assertNull($entity->getIdempotencyKey());
    }

    public function testRoundTripPreservesIdempotencyKey(): void
    {
        $idempotencyKey = 'idem_roundtrip_' . bin2hex(random_bytes(8));

        $original = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 7500,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            idempotencyKey: $idempotencyKey
        );

        // Domain -> Entity -> Domain round-trip
        $entity = PaymentEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertSame($idempotencyKey, $reconstituted->idempotencyKey());
        $this->assertSame($original->orderId(), $reconstituted->orderId());
        $this->assertSame($original->amountInCents(), $reconstituted->amountInCents());
    }

    public function testUpdateFromDomainModelPreservesIdempotencyKey(): void
    {
        $idempotencyKey = 'idem_update_' . bin2hex(random_bytes(8));

        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 3000,
            currency: 'GBP',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            idempotencyKey: $idempotencyKey
        );

        $entity = PaymentEntity::fromDomainModel($payment);

        // Simulate an update
        $entity->updateFromDomainModel($payment);

        $this->assertSame($idempotencyKey, $entity->getIdempotencyKey());
    }

    public function testSetterAndGetterForIdempotencyKey(): void
    {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 1000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $entity = PaymentEntity::fromDomainModel($payment);
        $this->assertNull($entity->getIdempotencyKey());

        $entity->setIdempotencyKey('manual_key_123');
        $this->assertSame('manual_key_123', $entity->getIdempotencyKey());
    }
}
