<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Loyalty Point Transaction Entity.
 *
 * Represents a single transaction in the loyalty points history.
 * This is an immutable audit trail - transactions cannot be modified after creation.
 *
 * Business Rules:
 * - Transaction ID is UUID v7 (time-ordered)
 * - Points can be positive (credit) or negative (debit)
 * - Balance after transaction must always be >= 0
 * - Reason must be between 1 and 255 characters
 * - Transactions are immutable (no update methods)
 * - Order ID is optional (only for order-related transactions)
 * - Expires at is optional (only for earned/bonus points with program validity)
 */
final class LoyaltyPointTransaction
{
    private LoyaltyPointTransactionId $id;
    private CustomerId $customerId;
    private TenantId $tenantId;
    private TransactionType $type;
    private int $points;
    private int $balanceAfter;
    private string $reason;
    private ?string $orderId;
    private ?\DateTimeImmutable $expiresAt;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        CustomerId $customerId,
        TenantId $tenantId,
        TransactionType $type,
        int $points,
        int $balanceAfter,
        string $reason,
        ?string $orderId = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): self {
        self::validatePoints($points);
        self::validateBalanceAfter($balanceAfter);
        self::validateReason($reason);

        $transaction = new self();
        $transaction->id = LoyaltyPointTransactionId::generate();
        $transaction->customerId = $customerId;
        $transaction->tenantId = $tenantId;
        $transaction->type = $type;
        $transaction->points = $points;
        $transaction->balanceAfter = $balanceAfter;
        $transaction->reason = trim($reason);
        $transaction->orderId = $orderId;
        $transaction->expiresAt = $expiresAt;
        $transaction->createdAt = new \DateTimeImmutable();

        return $transaction;
    }

    public static function reconstituteFromPersistence(
        LoyaltyPointTransactionId $id,
        CustomerId $customerId,
        TenantId $tenantId,
        TransactionType $type,
        int $points,
        int $balanceAfter,
        string $reason,
        ?string $orderId,
        ?\DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
    ): self {
        $transaction = new self();
        $transaction->id = $id;
        $transaction->customerId = $customerId;
        $transaction->tenantId = $tenantId;
        $transaction->type = $type;
        $transaction->points = $points;
        $transaction->balanceAfter = $balanceAfter;
        $transaction->reason = $reason;
        $transaction->orderId = $orderId;
        $transaction->expiresAt = $expiresAt;
        $transaction->createdAt = $createdAt;

        return $transaction;
    }

    /**
     * Check if this transaction is a debit (reduces points).
     */
    public function isDebit(): bool
    {
        return $this->type->isDebit();
    }

    /**
     * Check if this transaction is a credit (adds points).
     */
    public function isCredit(): bool
    {
        return $this->type->isCredit();
    }

    private static function validatePoints(int $points): void
    {
        if (0 === $points) {
            throw new \InvalidArgumentException('Transaction points cannot be zero');
        }
    }

    private static function validateBalanceAfter(int $balanceAfter): void
    {
        if ($balanceAfter < 0) {
            throw new \InvalidArgumentException(sprintf('Balance after transaction cannot be negative. Got: %d', $balanceAfter));
        }
    }

    private static function validateReason(string $reason): void
    {
        $trimmed = trim($reason);
        $length = mb_strlen($trimmed);

        if ($length < 1) {
            throw new \InvalidArgumentException('Transaction reason cannot be empty');
        }

        if ($length > 255) {
            throw new \InvalidArgumentException(sprintf('Transaction reason cannot exceed 255 characters. Got: %d', $length));
        }
    }

    // Getters
    public function id(): LoyaltyPointTransactionId
    {
        return $this->id;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function type(): TransactionType
    {
        return $this->type;
    }

    public function points(): int
    {
        return $this->points;
    }

    public function balanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function orderId(): ?string
    {
        return $this->orderId;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
