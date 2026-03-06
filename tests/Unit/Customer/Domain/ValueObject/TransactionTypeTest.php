<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\TransactionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionType::class)]
final class TransactionTypeTest extends TestCase
{
    // ---------------------------------------------------------------
    // isDebit() / isCredit()
    // ---------------------------------------------------------------

    #[Test]
    public function itIdentifiesRedeemedAsDebit(): void
    {
        self::assertTrue(TransactionType::REDEEMED->isDebit());
        self::assertFalse(TransactionType::REDEEMED->isCredit());
    }

    #[Test]
    public function itIdentifiesExpiredAsDebit(): void
    {
        self::assertTrue(TransactionType::EXPIRED->isDebit());
        self::assertFalse(TransactionType::EXPIRED->isCredit());
    }

    #[Test]
    public function itIdentifiesEarnedAsCredit(): void
    {
        self::assertTrue(TransactionType::EARNED->isCredit());
        self::assertFalse(TransactionType::EARNED->isDebit());
    }

    #[Test]
    public function itIdentifiesBonusAsCredit(): void
    {
        self::assertTrue(TransactionType::BONUS->isCredit());
        self::assertFalse(TransactionType::BONUS->isDebit());
    }

    #[Test]
    public function itIdentifiesAdjustmentAsCredit(): void
    {
        self::assertTrue(TransactionType::ADJUSTMENT->isCredit());
        self::assertFalse(TransactionType::ADJUSTMENT->isDebit());
    }

    // ---------------------------------------------------------------
    // label()
    // ---------------------------------------------------------------

    /** @return array<string, array{TransactionType, string}> */
    public static function labelProvider(): array
    {
        return [
            'earned' => [TransactionType::EARNED, 'Points Earned'],
            'redeemed' => [TransactionType::REDEEMED, 'Points Redeemed'],
            'expired' => [TransactionType::EXPIRED, 'Points Expired'],
            'bonus' => [TransactionType::BONUS, 'Bonus Points'],
            'adjustment' => [TransactionType::ADJUSTMENT, 'Manual Adjustment'],
        ];
    }

    #[Test]
    #[DataProvider('labelProvider')]
    public function itReturnsCorrectLabel(TransactionType $type, string $expected): void
    {
        self::assertSame($expected, $type->label());
    }

    // ---------------------------------------------------------------
    // fromString() / toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidString(): void
    {
        $type = TransactionType::fromString('earned');

        self::assertSame(TransactionType::EARNED, $type);
    }

    #[Test]
    public function itThrowsForInvalidString(): void
    {
        $this->expectException(\ValueError::class);

        TransactionType::fromString('invalid_type');
    }

    #[Test]
    public function itConvertsToString(): void
    {
        self::assertSame('earned', TransactionType::EARNED->toString());
        self::assertSame('redeemed', TransactionType::REDEEMED->toString());
        self::assertSame('expired', TransactionType::EXPIRED->toString());
        self::assertSame('bonus', TransactionType::BONUS->toString());
        self::assertSame('adjustment', TransactionType::ADJUSTMENT->toString());
    }

    // ---------------------------------------------------------------
    // Backed enum values
    // ---------------------------------------------------------------

    #[Test]
    public function itHasExpectedBackingValues(): void
    {
        self::assertSame('earned', TransactionType::EARNED->value);
        self::assertSame('redeemed', TransactionType::REDEEMED->value);
        self::assertSame('expired', TransactionType::EXPIRED->value);
        self::assertSame('bonus', TransactionType::BONUS->value);
        self::assertSame('adjustment', TransactionType::ADJUSTMENT->value);
    }
}
