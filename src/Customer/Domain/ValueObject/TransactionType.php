<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Transaction Type Enum.
 *
 * Represents the different types of loyalty point transactions.
 */
enum TransactionType: string
{
    case EARNED = 'earned';
    case REDEEMED = 'redeemed';
    case EXPIRED = 'expired';
    case BONUS = 'bonus';
    case ADJUSTMENT = 'adjustment';

    /**
     * Check if this transaction type is a debit (reduces points).
     */
    public function isDebit(): bool
    {
        return in_array($this, [self::REDEEMED, self::EXPIRED], true);
    }

    /**
     * Check if this transaction type is a credit (adds points).
     */
    public function isCredit(): bool
    {
        return in_array($this, [self::EARNED, self::BONUS, self::ADJUSTMENT], true);
    }

    /**
     * Get human-readable label for this transaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EARNED => 'Points Earned',
            self::REDEEMED => 'Points Redeemed',
            self::EXPIRED => 'Points Expired',
            self::BONUS => 'Bonus Points',
            self::ADJUSTMENT => 'Manual Adjustment',
        };
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
