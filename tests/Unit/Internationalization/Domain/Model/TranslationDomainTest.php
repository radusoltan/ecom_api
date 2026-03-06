<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\TranslationDomain;
use PHPUnit\Framework\TestCase;

final class TranslationDomainTest extends TestCase
{
    public function testFromStringCreateMessagesDomain(): void
    {
        $domain = TranslationDomain::fromString('messages');
        self::assertSame('messages', $domain->value());
    }

    public function testFromStringCreatesValidatorsDomain(): void
    {
        $domain = TranslationDomain::fromString('validators');
        self::assertSame('validators', $domain->value());
    }

    public function testFromStringCreatesEmailsDomain(): void
    {
        $domain = TranslationDomain::fromString('emails');
        self::assertSame('emails', $domain->value());
    }

    public function testFromStringCreatesAuthDomain(): void
    {
        $domain = TranslationDomain::fromString('auth');
        self::assertSame('auth', $domain->value());
    }

    public function testFromStringCreatesAdminDomain(): void
    {
        $domain = TranslationDomain::fromString('admin');
        self::assertSame('admin', $domain->value());
    }

    public function testFromStringCreatesCatalogDomain(): void
    {
        $domain = TranslationDomain::fromString('catalog');
        self::assertSame('catalog', $domain->value());
    }

    public function testFromStringCreatesInventoryDomain(): void
    {
        $domain = TranslationDomain::fromString('inventory');
        self::assertSame('inventory', $domain->value());
    }

    public function testFromStringCreatesOrderDomain(): void
    {
        $domain = TranslationDomain::fromString('order');
        self::assertSame('order', $domain->value());
    }

    public function testFromStringCreatesCustomerDomain(): void
    {
        $domain = TranslationDomain::fromString('customer');
        self::assertSame('customer', $domain->value());
    }

    public function testFromStringCreatesPricingDomain(): void
    {
        $domain = TranslationDomain::fromString('pricing');
        self::assertSame('pricing', $domain->value());
    }

    public function testFromStringThrowsForUnsupportedDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid translation domain "unknown"');
        TranslationDomain::fromString('unknown');
    }

    public function testFromStringThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationDomain::fromString('');
    }

    public function testFromStringThrowsForArbitraryString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported domains:');
        TranslationDomain::fromString('products');
    }

    public function testEqualsReturnsTrueForSameDomain(): void
    {
        $domain1 = TranslationDomain::fromString('messages');
        $domain2 = TranslationDomain::fromString('messages');
        self::assertTrue($domain1->equals($domain2));
    }

    public function testEqualsReturnsFalseForDifferentDomain(): void
    {
        $domain1 = TranslationDomain::fromString('messages');
        $domain2 = TranslationDomain::fromString('validators');
        self::assertFalse($domain1->equals($domain2));
    }

    public function testAllReturnsTenSupportedDomains(): void
    {
        $all = TranslationDomain::all();
        self::assertCount(10, $all);
        self::assertContains('messages', $all);
        self::assertContains('validators', $all);
        self::assertContains('emails', $all);
        self::assertContains('auth', $all);
        self::assertContains('admin', $all);
        self::assertContains('catalog', $all);
        self::assertContains('inventory', $all);
        self::assertContains('order', $all);
        self::assertContains('customer', $all);
        self::assertContains('pricing', $all);
    }

    public function testAllReturnsArray(): void
    {
        self::assertIsArray(TranslationDomain::all());
    }

    public function testErrorMessageIncludesSupportedDomains(): void
    {
        try {
            TranslationDomain::fromString('invalid');
            self::fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('messages', $e->getMessage());
            self::assertStringContainsString('validators', $e->getMessage());
        }
    }
}
