<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PaymentMethodTest extends TestCase
{
    // ==================== Enum Cases ====================

    public function testAllEnumCasesExist(): void
    {
        $cases = PaymentMethod::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(PaymentMethod::STRIPE, $cases);
        $this->assertContains(PaymentMethod::PAYPAL, $cases);
        $this->assertContains(PaymentMethod::BANK_TRANSFER, $cases);
        $this->assertContains(PaymentMethod::APPLE_PAY, $cases);
        $this->assertContains(PaymentMethod::GOOGLE_PAY, $cases);
    }

    // ==================== isCard Method ====================

    public function testIsCardReturnsTrueForStripe(): void
    {
        $this->assertTrue(PaymentMethod::STRIPE->isCard());
    }

    public function testIsCardReturnsFalseForPaypal(): void
    {
        $this->assertFalse(PaymentMethod::PAYPAL->isCard());
    }

    public function testIsCardReturnsFalseForBankTransfer(): void
    {
        $this->assertFalse(PaymentMethod::BANK_TRANSFER->isCard());
    }

    // ==================== requiresRedirect Method ====================

    public function testRequiresRedirectReturnsTrueForPaypal(): void
    {
        $this->assertTrue(PaymentMethod::PAYPAL->requiresRedirect());
    }

    public function testRequiresRedirectReturnsFalseForStripe(): void
    {
        $this->assertFalse(PaymentMethod::STRIPE->requiresRedirect());
    }

    public function testRequiresRedirectReturnsFalseForBankTransfer(): void
    {
        $this->assertFalse(PaymentMethod::BANK_TRANSFER->requiresRedirect());
    }

    // ==================== requiresManualVerification Method ====================

    public function testRequiresManualVerificationReturnsTrueForBankTransfer(): void
    {
        $this->assertTrue(PaymentMethod::BANK_TRANSFER->requiresManualVerification());
    }

    public function testRequiresManualVerificationReturnsFalseForStripe(): void
    {
        $this->assertFalse(PaymentMethod::STRIPE->requiresManualVerification());
    }

    public function testRequiresManualVerificationReturnsFalseForPaypal(): void
    {
        $this->assertFalse(PaymentMethod::PAYPAL->requiresManualVerification());
    }

    // ==================== description Method ====================

    public function testDescriptionReturnsCorrectTextForStripe(): void
    {
        $this->assertSame('Credit/Debit Card (Stripe)', PaymentMethod::STRIPE->description());
    }

    public function testDescriptionReturnsCorrectTextForPaypal(): void
    {
        $this->assertSame('PayPal', PaymentMethod::PAYPAL->description());
    }

    public function testDescriptionReturnsCorrectTextForBankTransfer(): void
    {
        $this->assertSame('Bank Transfer', PaymentMethod::BANK_TRANSFER->description());
    }

    // ==================== value Method ====================

    public function testValueReturnsStringValue(): void
    {
        $this->assertSame('stripe', PaymentMethod::STRIPE->value());
        $this->assertSame('paypal', PaymentMethod::PAYPAL->value());
        $this->assertSame('bank_transfer', PaymentMethod::BANK_TRANSFER->value());
    }

    // ==================== Enum String Values ====================

    public function testEnumStringValues(): void
    {
        $this->assertSame('stripe', PaymentMethod::STRIPE->value);
        $this->assertSame('paypal', PaymentMethod::PAYPAL->value);
        $this->assertSame('bank_transfer', PaymentMethod::BANK_TRANSFER->value);
    }
}
