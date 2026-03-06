<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\ValueObject;

use App\Tenant\Domain\ValueObject\TenantQuotas;
use PHPUnit\Framework\TestCase;

final class TenantQuotasTest extends TestCase
{
    public function testStarterTierReturnsCorrectLimits(): void
    {
        $quotas = TenantQuotas::forTier('starter');

        $this->assertSame(100, $quotas->maxProducts);
        $this->assertSame(500, $quotas->maxOrdersPerMonth);
        $this->assertSame(1024, $quotas->maxStorageMb);
        $this->assertSame(1000, $quotas->maxTranslations);
        $this->assertSame(1, $quotas->maxWarehouses);
        $this->assertSame(3, $quotas->maxUsers);
    }

    public function testProfessionalTierReturnsCorrectLimits(): void
    {
        $quotas = TenantQuotas::forTier('professional');

        $this->assertSame(5000, $quotas->maxProducts);
        $this->assertSame(10000, $quotas->maxOrdersPerMonth);
        $this->assertSame(10240, $quotas->maxStorageMb);
        $this->assertSame(10000, $quotas->maxTranslations);
        $this->assertSame(5, $quotas->maxWarehouses);
        $this->assertSame(25, $quotas->maxUsers);
    }

    public function testEnterpriseTierReturnsCorrectLimits(): void
    {
        $quotas = TenantQuotas::forTier('enterprise');

        $this->assertSame(PHP_INT_MAX, $quotas->maxProducts);
        $this->assertSame(PHP_INT_MAX, $quotas->maxOrdersPerMonth);
        $this->assertSame(102400, $quotas->maxStorageMb);
        $this->assertSame(PHP_INT_MAX, $quotas->maxTranslations);
        $this->assertSame(PHP_INT_MAX, $quotas->maxWarehouses);
        $this->assertSame(PHP_INT_MAX, $quotas->maxUsers);
    }

    public function testUnknownTierThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tier: free');

        TenantQuotas::forTier('free');
    }

    public function testEqualsReturnsTrueForSameValues(): void
    {
        $quotas1 = TenantQuotas::forTier('starter');
        $quotas2 = TenantQuotas::forTier('starter');

        $this->assertTrue($quotas1->equals($quotas2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $quotas1 = TenantQuotas::forTier('starter');
        $quotas2 = TenantQuotas::forTier('professional');

        $this->assertFalse($quotas1->equals($quotas2));
    }

    public function testEqualsReturnsTrueForManuallyConstructedSameValues(): void
    {
        $quotas1 = new TenantQuotas(100, 500, 1024, 1000, 1, 3);
        $quotas2 = new TenantQuotas(100, 500, 1024, 1000, 1, 3);

        $this->assertTrue($quotas1->equals($quotas2));
    }

    public function testEqualsReturnsFalseWhenOnlyOneFieldDiffers(): void
    {
        $quotas1 = new TenantQuotas(100, 500, 1024, 1000, 1, 3);
        $quotas2 = new TenantQuotas(100, 500, 1024, 1000, 1, 4);

        $this->assertFalse($quotas1->equals($quotas2));
    }

    public function testConstructorRejectsNegativeMaxProducts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxProducts cannot be negative');

        new TenantQuotas(-1, 500, 1024, 1000, 1, 3);
    }

    public function testConstructorRejectsNegativeMaxOrdersPerMonth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxOrdersPerMonth cannot be negative');

        new TenantQuotas(100, -1, 1024, 1000, 1, 3);
    }

    public function testConstructorRejectsNegativeMaxStorageMb(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxStorageMb cannot be negative');

        new TenantQuotas(100, 500, -1, 1000, 1, 3);
    }

    public function testConstructorRejectsNegativeMaxTranslations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxTranslations cannot be negative');

        new TenantQuotas(100, 500, 1024, -1, 1, 3);
    }

    public function testConstructorRejectsNegativeMaxWarehouses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxWarehouses cannot be negative');

        new TenantQuotas(100, 500, 1024, 1000, -1, 3);
    }

    public function testConstructorRejectsNegativeMaxUsers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxUsers cannot be negative');

        new TenantQuotas(100, 500, 1024, 1000, 1, -1);
    }

    public function testConstructorAcceptsZeroValues(): void
    {
        $quotas = new TenantQuotas(0, 0, 0, 0, 0, 0);

        $this->assertSame(0, $quotas->maxProducts);
        $this->assertSame(0, $quotas->maxOrdersPerMonth);
        $this->assertSame(0, $quotas->maxStorageMb);
        $this->assertSame(0, $quotas->maxTranslations);
        $this->assertSame(0, $quotas->maxWarehouses);
        $this->assertSame(0, $quotas->maxUsers);
    }
}
