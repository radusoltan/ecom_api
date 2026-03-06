<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Persistence\Doctrine\Entity;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PaymentEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            $paymentId, $tenantId, 'order-123',
            Money::fromScalars(5000, 'EUR'),
            PaymentMethod::fromString('card'),
            PaymentGateway::fromString('stripe'),
            'idem-key-001',
        );

        $entity = PaymentEntity::fromDomainModel($payment);

        self::assertSame($paymentId->toString(), $entity->getId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('order-123', $entity->getOrderId());
        self::assertSame(5000, $entity->getAmountInCents());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('card', $entity->getMethod());
        self::assertSame('stripe', $entity->getGateway());
        self::assertSame('pending', $entity->getStatus());
        self::assertNull($entity->getGatewayTransactionId());
        self::assertNull($entity->getErrorMessage());
        self::assertNull($entity->getErrorCode());
        self::assertSame('idem-key-001', $entity->getIdempotencyKey());
        self::assertSame(0, $entity->getRefundedAmountInCents());
        self::assertSame(0, $entity->getRetryCount());
        self::assertNull($entity->getNextRetryAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($paymentId));
        self::assertSame('order-123', $restored->orderId());
        self::assertSame('pending', $restored->status()->value());
    }

    public function testUpdateFromDomainModel(): void
    {
        $payment = Payment::create(
            PaymentId::generate(), TenantId::generate(), 'order-456',
            Money::fromScalars(3000, 'USD'),
            PaymentMethod::fromString('card'),
            PaymentGateway::fromString('stripe'),
        );

        $entity = PaymentEntity::fromDomainModel($payment);
        $payment->authorize('gw-tx-001');
        $entity->updateFromDomainModel($payment);

        self::assertSame('authorized', $entity->getStatus());
        self::assertSame('gw-tx-001', $entity->getGatewayTransactionId());
    }

    public function testSetters(): void
    {
        $entity = new PaymentEntity();
        $entity->setId('pay-001');
        $entity->setTenantId('tenant-001');
        $entity->setOrderId('order-001');
        $entity->setAmountInCents(2500);
        $entity->setCurrency('GBP');
        $entity->setMethod('bank_transfer');
        $entity->setGateway('paypal');
        $entity->setStatus('captured');
        $entity->setGatewayTransactionId('gw-123');
        $entity->setErrorMessage('timeout');
        $entity->setErrorCode('ERR_TIMEOUT');
        $entity->setIdempotencyKey('idem-999');
        $entity->setRefundedAmountInCents(500);
        $entity->setRetryCount(2);
        $nextRetry = new \DateTimeImmutable('+1 hour');
        $entity->setNextRetryAt($nextRetry);
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        self::assertSame('pay-001', $entity->getId());
        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('order-001', $entity->getOrderId());
        self::assertSame(2500, $entity->getAmountInCents());
        self::assertSame('GBP', $entity->getCurrency());
        self::assertSame('bank_transfer', $entity->getMethod());
        self::assertSame('paypal', $entity->getGateway());
        self::assertSame('captured', $entity->getStatus());
        self::assertSame('gw-123', $entity->getGatewayTransactionId());
        self::assertSame('timeout', $entity->getErrorMessage());
        self::assertSame('ERR_TIMEOUT', $entity->getErrorCode());
        self::assertSame('idem-999', $entity->getIdempotencyKey());
        self::assertSame(500, $entity->getRefundedAmountInCents());
        self::assertSame(2, $entity->getRetryCount());
        self::assertSame($nextRetry, $entity->getNextRetryAt());
    }
}
