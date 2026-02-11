<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\TransactionType;
use PHPUnit\Framework\TestCase;

final class TransactionTypeTest extends TestCase
{
    // ==================== Enum Cases ====================

    public function testAllEnumCasesExist(): void
    {
        $cases = TransactionType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(TransactionType::AUTHORIZATION, $cases);
        $this->assertContains(TransactionType::CAPTURE, $cases);
        $this->assertContains(TransactionType::REFUND, $cases);
        $this->assertContains(TransactionType::VOID, $cases);
        $this->assertContains(TransactionType::CHARGEBACK, $cases);
    }

    // ==================== affectsBalance Method ====================

    public function testAffectsBalanceReturnsFalseForAuthorization(): void
    {
        $this->assertFalse(TransactionType::AUTHORIZATION->affectsBalance());
    }

    public function testAffectsBalanceReturnsTrueForCapture(): void
    {
        $this->assertTrue(TransactionType::CAPTURE->affectsBalance());
    }

    public function testAffectsBalanceReturnsTrueForRefund(): void
    {
        $this->assertTrue(TransactionType::REFUND->affectsBalance());
    }

    public function testAffectsBalanceReturnsFalseForVoid(): void
    {
        $this->assertFalse(TransactionType::VOID->affectsBalance());
    }

    public function testAffectsBalanceReturnsTrueForChargeback(): void
    {
        $this->assertTrue(TransactionType::CHARGEBACK->affectsBalance());
    }

    // ==================== balanceMultiplier Method ====================

    public function testBalanceMultiplierReturnsZeroForAuthorization(): void
    {
        $this->assertSame(0, TransactionType::AUTHORIZATION->balanceMultiplier());
    }

    public function testBalanceMultiplierReturnsOneForCapture(): void
    {
        $this->assertSame(1, TransactionType::CAPTURE->balanceMultiplier());
    }

    public function testBalanceMultiplierReturnsNegativeOneForRefund(): void
    {
        $this->assertSame(-1, TransactionType::REFUND->balanceMultiplier());
    }

    public function testBalanceMultiplierReturnsZeroForVoid(): void
    {
        $this->assertSame(0, TransactionType::VOID->balanceMultiplier());
    }

    public function testBalanceMultiplierReturnsNegativeOneForChargeback(): void
    {
        $this->assertSame(-1, TransactionType::CHARGEBACK->balanceMultiplier());
    }

    // ==================== description Method ====================

    public function testDescriptionReturnsCorrectTextForAuthorization(): void
    {
        $this->assertSame('Authorization (funds reserved)', TransactionType::AUTHORIZATION->description());
    }

    public function testDescriptionReturnsCorrectTextForCapture(): void
    {
        $this->assertSame('Capture (funds collected)', TransactionType::CAPTURE->description());
    }

    public function testDescriptionReturnsCorrectTextForRefund(): void
    {
        $this->assertSame('Refund (funds returned)', TransactionType::REFUND->description());
    }

    public function testDescriptionReturnsCorrectTextForVoid(): void
    {
        $this->assertSame('Void (authorization cancelled)', TransactionType::VOID->description());
    }

    public function testDescriptionReturnsCorrectTextForChargeback(): void
    {
        $this->assertSame('Chargeback (customer dispute)', TransactionType::CHARGEBACK->description());
    }

    // ==================== Individual Type Checkers ====================

    public function testIsAuthorizationReturnsTrue(): void
    {
        $this->assertTrue(TransactionType::AUTHORIZATION->isAuthorization());
        $this->assertFalse(TransactionType::CAPTURE->isAuthorization());
    }

    public function testIsCaptureReturnsTrue(): void
    {
        $this->assertTrue(TransactionType::CAPTURE->isCapture());
        $this->assertFalse(TransactionType::AUTHORIZATION->isCapture());
    }

    public function testIsRefundReturnsTrue(): void
    {
        $this->assertTrue(TransactionType::REFUND->isRefund());
        $this->assertFalse(TransactionType::CAPTURE->isRefund());
    }

    public function testIsVoidReturnsTrue(): void
    {
        $this->assertTrue(TransactionType::VOID->isVoid());
        $this->assertFalse(TransactionType::AUTHORIZATION->isVoid());
    }

    public function testIsChargebackReturnsTrue(): void
    {
        $this->assertTrue(TransactionType::CHARGEBACK->isChargeback());
        $this->assertFalse(TransactionType::REFUND->isChargeback());
    }

    // ==================== value Method ====================

    public function testValueReturnsStringValue(): void
    {
        $this->assertSame('authorization', TransactionType::AUTHORIZATION->value());
        $this->assertSame('capture', TransactionType::CAPTURE->value());
        $this->assertSame('refund', TransactionType::REFUND->value());
        $this->assertSame('void', TransactionType::VOID->value());
        $this->assertSame('chargeback', TransactionType::CHARGEBACK->value());
    }

    // ==================== Enum String Values ====================

    public function testEnumStringValues(): void
    {
        $this->assertSame('authorization', TransactionType::AUTHORIZATION->value);
        $this->assertSame('capture', TransactionType::CAPTURE->value);
        $this->assertSame('refund', TransactionType::REFUND->value);
        $this->assertSame('void', TransactionType::VOID->value);
        $this->assertSame('chargeback', TransactionType::CHARGEBACK->value);
    }
}
