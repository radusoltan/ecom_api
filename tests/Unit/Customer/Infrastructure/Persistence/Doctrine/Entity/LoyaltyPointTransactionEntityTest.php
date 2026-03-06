<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyPointTransactionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class LoyaltyPointTransactionEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $id = LoyaltyPointTransactionId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $type = TransactionType::EARNED;
        $points = 100;
        $balanceAfter = 500;
        $reason = 'Purchase reward';
        $orderId = 'order-uuid-1234';
        $expiresAt = new \DateTimeImmutable('2027-01-01T00:00:00+00:00');
        $createdAt = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');

        $transaction = LoyaltyPointTransaction::reconstituteFromPersistence(
            $id,
            $customerId,
            $tenantId,
            $type,
            $points,
            $balanceAfter,
            $reason,
            $orderId,
            $expiresAt,
            $createdAt,
        );

        $entity = LoyaltyPointTransactionEntity::fromDomainModel($transaction);

        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($customerId->toString(), $entity->getCustomerId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('earned', $entity->getType());
        self::assertSame(100, $entity->getPoints());
        self::assertSame(500, $entity->getBalanceAfter());
        self::assertSame('Purchase reward', $entity->getReason());
        self::assertSame('order-uuid-1234', $entity->getOrderId());
        self::assertSame($expiresAt, $entity->getExpiresAt());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertNull($entity->getCustomer());

        // Roundtrip to domain model
        $restored = $entity->toDomainModel();
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($customerId->toString(), $restored->customerId()->toString());
        self::assertSame($tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame(TransactionType::EARNED, $restored->type());
        self::assertSame(100, $restored->points());
        self::assertSame(500, $restored->balanceAfter());
        self::assertSame('Purchase reward', $restored->reason());
        self::assertSame('order-uuid-1234', $restored->orderId());
        self::assertSame($expiresAt, $restored->expiresAt());
        self::assertSame($createdAt, $restored->createdAt());
    }

    public function testFromDomainModelWithNullOptionalFields(): void
    {
        $id = LoyaltyPointTransactionId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');

        $transaction = LoyaltyPointTransaction::reconstituteFromPersistence(
            $id,
            $customerId,
            $tenantId,
            TransactionType::REDEEMED,
            -50,
            150,
            'Redeemed for discount',
            null,
            null,
            $createdAt,
        );

        $entity = LoyaltyPointTransactionEntity::fromDomainModel($transaction);

        self::assertNull($entity->getOrderId());
        self::assertNull($entity->getExpiresAt());
        self::assertSame('redeemed', $entity->getType());
        self::assertSame(-50, $entity->getPoints());
        self::assertSame(150, $entity->getBalanceAfter());

        $restored = $entity->toDomainModel();
        self::assertNull($restored->orderId());
        self::assertNull($restored->expiresAt());
    }

    public function testGettersReflectAllTransactionTypes(): void
    {
        $types = [
            TransactionType::EARNED,
            TransactionType::REDEEMED,
            TransactionType::EXPIRED,
            TransactionType::BONUS,
            TransactionType::ADJUSTMENT,
        ];

        foreach ($types as $type) {
            $points = $type->isDebit() ? -10 : 10;
            $balanceAfter = $type->isDebit() ? 90 : 110;

            $transaction = LoyaltyPointTransaction::reconstituteFromPersistence(
                LoyaltyPointTransactionId::generate(),
                CustomerId::generate(),
                TenantId::generate(),
                $type,
                $points,
                $balanceAfter,
                'Test transaction',
                null,
                null,
                new \DateTimeImmutable(),
            );

            $entity = LoyaltyPointTransactionEntity::fromDomainModel($transaction);
            self::assertSame($type->value, $entity->getType());

            $restored = $entity->toDomainModel();
            self::assertSame($type, $restored->type());
        }
    }
}
