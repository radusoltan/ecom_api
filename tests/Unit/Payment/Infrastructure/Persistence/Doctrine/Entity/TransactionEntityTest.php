<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Persistence\Doctrine\Entity;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class TransactionEntityTest extends TestCase
{
    public function testFromDomainModelRoundtripAuthorization(): void
    {
        $transaction = Transaction::createAuthorization(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(5000, 'EUR'),
            'pi_stripe_123',
            '{"status":"requires_capture"}',
        );

        $entity = TransactionEntity::fromDomainModel($transaction);

        self::assertSame('authorization', $entity->getType());
        self::assertSame(5000, $entity->getAmountInCents());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('pi_stripe_123', $entity->getGatewayTransactionId());
        self::assertSame('success', $entity->getStatus());
        self::assertNull($entity->getErrorCode());
        self::assertNull($entity->getErrorMessage());
        self::assertNotNull($entity->getGatewayResponse());

        $restored = $entity->toDomainModel();
        self::assertSame('authorization', $restored->type()->value);
        self::assertSame('success', $restored->status()->value);
    }

    public function testFromDomainModelCapture(): void
    {
        $transaction = Transaction::createCapture(
            TransactionId::generate(),
            PaymentId::generate(),
            Money::fromScalars(3000, 'USD'),
            'ch_stripe_456',
        );

        $entity = TransactionEntity::fromDomainModel($transaction);
        self::assertSame('capture', $entity->getType());
        self::assertSame(3000, $entity->getAmountInCents());
        self::assertSame('USD', $entity->getCurrency());
    }

    public function testSetters(): void
    {
        $entity = new TransactionEntity();
        $entity->setId('tx-001');
        $entity->setPaymentId('pay-001');
        $entity->setType('refund');
        $entity->setAmountInCents(1500);
        $entity->setCurrency('GBP');
        $entity->setGatewayTransactionId('re_test');
        $entity->setGatewayResponse('{}');
        $entity->setStatus('success');
        $entity->setErrorCode(null);
        $entity->setErrorMessage(null);
        $entity->setCreatedAt(new \DateTimeImmutable());

        self::assertSame('tx-001', $entity->getId());
        self::assertSame('pay-001', $entity->getPaymentId());
        self::assertSame('refund', $entity->getType());
        self::assertSame(1500, $entity->getAmountInCents());
        self::assertSame('GBP', $entity->getCurrency());
        self::assertSame('re_test', $entity->getGatewayTransactionId());
        self::assertSame('success', $entity->getStatus());
    }
}
