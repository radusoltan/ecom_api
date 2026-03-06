<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyPointTransaction::class)]
final class LoyaltyPointTransactionTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    // -----------------------------------------------------------------------
    // create — happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesEarnedTransaction(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 100,
            balanceAfter: 100,
            reason: 'Purchase reward',
        );

        self::assertInstanceOf(LoyaltyPointTransactionId::class, $transaction->id());
        self::assertTrue($this->customerId->equals($transaction->customerId()));
        self::assertTrue($this->tenantId->equals($transaction->tenantId()));
        self::assertSame(TransactionType::EARNED, $transaction->type());
        self::assertSame(100, $transaction->points());
        self::assertSame(100, $transaction->balanceAfter());
        self::assertSame('Purchase reward', $transaction->reason());
        self::assertNull($transaction->orderId());
        self::assertNull($transaction->expiresAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $transaction->createdAt());
    }

    #[Test]
    public function itCreatesRedeemedTransaction(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::REDEEMED,
            points: -50,
            balanceAfter: 50,
            reason: 'Discount applied',
        );

        self::assertSame(TransactionType::REDEEMED, $transaction->type());
        self::assertSame(-50, $transaction->points());
        self::assertSame(50, $transaction->balanceAfter());
    }

    #[Test]
    public function itCreatesBonusTransaction(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::BONUS,
            points: 200,
            balanceAfter: 300,
            reason: 'Sign-up bonus',
        );

        self::assertSame(TransactionType::BONUS, $transaction->type());
        self::assertTrue($transaction->isCredit());
        self::assertFalse($transaction->isDebit());
    }

    #[Test]
    public function itCreatesAdjustmentTransaction(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::ADJUSTMENT,
            points: 10,
            balanceAfter: 110,
            reason: 'Manual correction',
        );

        self::assertSame(TransactionType::ADJUSTMENT, $transaction->type());
        self::assertTrue($transaction->isCredit());
    }

    #[Test]
    public function itCreatesExpiredTransaction(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EXPIRED,
            points: -30,
            balanceAfter: 0,
            reason: 'Points expired',
        );

        self::assertSame(TransactionType::EXPIRED, $transaction->type());
        self::assertTrue($transaction->isDebit());
        self::assertFalse($transaction->isCredit());
    }

    #[Test]
    public function itCreatesTransactionWithOptionalFields(): void
    {
        $expiresAt = new \DateTimeImmutable('+365 days');

        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 50,
            balanceAfter: 150,
            reason: 'Order reward',
            orderId: 'order-uuid-1234',
            expiresAt: $expiresAt,
        );

        self::assertSame('order-uuid-1234', $transaction->orderId());
        self::assertEquals($expiresAt, $transaction->expiresAt());
    }

    #[Test]
    public function itTrimsReasonWhitespace(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: '   Purchase reward   ',
        );

        self::assertSame('Purchase reward', $transaction->reason());
    }

    // -----------------------------------------------------------------------
    // create — validation failures
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPointsAreZero(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Transaction points cannot be zero');

        LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 0,
            balanceAfter: 0,
            reason: 'Zero points',
        );
    }

    #[Test]
    public function itThrowsWhenBalanceAfterIsNegative(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Balance after transaction cannot be negative');

        LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::REDEEMED,
            points: -100,
            balanceAfter: -10,
            reason: 'Overdraft attempt',
        );
    }

    #[Test]
    public function itThrowsWhenReasonIsEmpty(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Transaction reason cannot be empty');

        LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: '',
        );
    }

    #[Test]
    public function itThrowsWhenReasonIsOnlyWhitespace(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Transaction reason cannot be empty');

        LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: '   ',
        );
    }

    #[Test]
    public function itThrowsWhenReasonExceeds255Characters(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Transaction reason cannot exceed 255 characters');

        LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: str_repeat('A', 256),
        );
    }

    #[Test]
    public function itAcceptsReasonOfExactly255Characters(): void
    {
        $reason = str_repeat('X', 255);

        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: $reason,
        );

        self::assertSame($reason, $transaction->reason());
    }

    #[Test]
    public function itAcceptsBalanceAfterOfZero(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::REDEEMED,
            points: -100,
            balanceAfter: 0, // Zero balance is valid
            reason: 'Spent all points',
        );

        self::assertSame(0, $transaction->balanceAfter());
    }

    // -----------------------------------------------------------------------
    // isDebit / isCredit
    // -----------------------------------------------------------------------

    #[Test]
    public function itEarnedTransactionIsCredit(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 100,
            balanceAfter: 100,
            reason: 'Earned',
        );

        self::assertTrue($transaction->isCredit());
        self::assertFalse($transaction->isDebit());
    }

    #[Test]
    public function itRedeemedTransactionIsDebit(): void
    {
        $transaction = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::REDEEMED,
            points: -50,
            balanceAfter: 50,
            reason: 'Redeemed',
        );

        self::assertTrue($transaction->isDebit());
        self::assertFalse($transaction->isCredit());
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceRestoresAllFields(): void
    {
        $id = LoyaltyPointTransactionId::generate();
        $expiresAt = new \DateTimeImmutable('+180 days');
        $createdAt = new \DateTimeImmutable('-3 days');

        $transaction = LoyaltyPointTransaction::reconstituteFromPersistence(
            id: $id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::BONUS,
            points: 500,
            balanceAfter: 750,
            reason: 'Welcome bonus',
            orderId: 'order-abc',
            expiresAt: $expiresAt,
            createdAt: $createdAt,
        );

        self::assertTrue($id->equals($transaction->id()));
        self::assertTrue($this->customerId->equals($transaction->customerId()));
        self::assertTrue($this->tenantId->equals($transaction->tenantId()));
        self::assertSame(TransactionType::BONUS, $transaction->type());
        self::assertSame(500, $transaction->points());
        self::assertSame(750, $transaction->balanceAfter());
        self::assertSame('Welcome bonus', $transaction->reason());
        self::assertSame('order-abc', $transaction->orderId());
        self::assertEquals($expiresAt, $transaction->expiresAt());
        self::assertEquals($createdAt, $transaction->createdAt());
    }

    #[Test]
    public function itReconstituteFromPersistenceWithNullOptionals(): void
    {
        $transaction = LoyaltyPointTransaction::reconstituteFromPersistence(
            id: LoyaltyPointTransactionId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 50,
            balanceAfter: 50,
            reason: 'Earned',
            orderId: null,
            expiresAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertNull($transaction->orderId());
        self::assertNull($transaction->expiresAt());
    }

    // -----------------------------------------------------------------------
    // generate creates unique IDs
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueTransactionIds(): void
    {
        $tx1 = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 10,
            balanceAfter: 10,
            reason: 'First',
        );

        $tx2 = LoyaltyPointTransaction::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            type: TransactionType::EARNED,
            points: 20,
            balanceAfter: 30,
            reason: 'Second',
        );

        self::assertFalse($tx1->id()->equals($tx2->id()));
    }
}
